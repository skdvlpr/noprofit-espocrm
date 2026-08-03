<?php

namespace Espo\Modules\VolunteerActivityDispatch\Tools;

use Espo\Core\InjectableFactory;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Email;
use Espo\Entities\EmailTemplate;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate\TemplatePlaceholderHelper;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Params;
use Espo\Tools\EmailTemplate\Processor;
use Throwable;

/**
 * Sends shift planning emails to volunteers using admin-editable
 * EmailTemplate records (provisioned by the Installer, IDs kept in config
 * key `vadEmailTemplateIds`).
 *
 * Extra tokens replaced after template processing:
 *   {recordUrl} / {planUrl} — deep link to the shift plan (planUrl kept as alias)
 *   {shiftList}             — recipient's confirmed shifts (HTML lines)
 *   {slotList}              — all shifts in the plan (HTML lines)
 *   {slotCount}             — number of shifts in the plan
 */
class ShiftEmailService
{
    public const CONFIG_KEY = 'vadEmailTemplateIds';

    public const KIND_AVAILABILITY_REQUEST = 'availabilityRequest';
    public const KIND_SHIFTS_CONFIRMED = 'shiftsConfirmed';

    public function __construct(
        private EntityManager $entityManager,
        private EmailSender $emailSender,
        private Config $config,
        private InjectableFactory $injectableFactory,
        private Log $log,
        private TemplatePlaceholderHelper $placeholderHelper,
        private Language $language,
    ) {}

    /**
     * @param string[] $userIds
     */
    public function sendAvailabilityRequest(Entity $offer, array $userIds): int
    {
        return $this->sendTemplated(self::KIND_AVAILABILITY_REQUEST, $offer, $userIds, []);
    }

    /**
     * @param string[] $shiftLines
     */
    public function sendShiftsConfirmed(Entity $offer, string $userId, array $shiftLines): int
    {
        return $this->sendTemplated(self::KIND_SHIFTS_CONFIRMED, $offer, [$userId], $shiftLines);
    }

    /**
     * @param string[] $userIds
     * @param string[] $shiftLines
     */
    private function sendTemplated(string $kind, Entity $offer, array $userIds, array $shiftLines): int
    {
        $template = $this->getTemplate($kind);

        if (!$template) {
            $this->log->warning(
                "Shift planning: email template '$kind' is not provisioned — emails skipped."
            );

            return 0;
        }

        $from = $this->config->get('outboundEmailFromAddress');

        if (!$from) {
            $this->log->warning(
                'Shift planning: system outbound email address is not configured — emails skipped.'
            );

            return 0;
        }

        $processor = $this->injectableFactory->create(Processor::class);
        $params = Params::create()->withApplyAcl(false)->withCopyAttachments(false);

        $slots = $this->loadSlots($offer->getId());
        $slotLines = array_map(fn (Entity $slot): string => $this->formatSlotLine($slot), $slots);
        $slotListHtml = $this->linesToHtml($slotLines);
        $shiftListHtml = $this->linesToHtml($shiftLines);

        $extra = [
            '{shiftList}' => $shiftListHtml,
            '{slotList}' => $slotListHtml,
            '{slotCount}' => (string) count($slots),
        ];

        $sent = 0;

        foreach ($userIds as $userId) {
            /** @var ?User $recipient */
            $recipient = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

            if (!$recipient || !$recipient->get('isActive')) {
                continue;
            }

            $address = $this->getPrimaryEmailAddress($recipient);

            if ($address === '') {
                continue;
            }

            try {
                $entityHash = [
                    User::ENTITY_TYPE => $recipient,
                    $offer->getEntityType() => $offer,
                ];

                $data = Data::create()
                    ->withParent($offer)
                    ->withUser($recipient)
                    ->withEntityHash($entityHash);

                $result = $processor->process($template, $params, $data);

                $subject = strtr($result->getSubject(), $extra);
                $body = strtr($result->getBody(), $extra);

                $subject = $this->placeholderHelper->applyRecordUrls($subject, $offer, $entityHash);
                $body = $this->placeholderHelper->applyRecordUrls($body, $offer, $entityHash);

                $subject = $this->placeholderHelper->clearUnresolvedEntityPlaceholders($subject);
                $body = $this->placeholderHelper->clearUnresolvedEntityPlaceholders($body);

                /** @var Email $email */
                $email = $this->entityManager->getRDBRepositoryByClass(Email::class)->getNew();
                $email->set([
                    'subject' => $subject,
                    'body' => $body,
                    'isHtml' => $result->isHtml(),
                    'from' => $from,
                    'to' => $address,
                    'isSystem' => true,
                    'parentId' => $offer->getId(),
                    'parentType' => $offer->getEntityType(),
                ]);

                $this->emailSender->send($email);
                $sent++;
            } catch (Throwable $e) {
                $this->log->warning(
                    'Shift planning: email to {address} failed: {message}',
                    ['address' => $address, 'message' => $e->getMessage()]
                );
            }
        }

        return $sent;
    }

    /**
     * @return Entity[]
     */
    private function loadSlots(string $offerId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('ActivityOfferSlot')
            ->where(['activityOfferId' => $offerId])
            ->order('dateStart')
            ->find();

        return iterator_to_array($collection);
    }

    private function formatSlotLine(Entity $slot): string
    {
        $category = (string) ($slot->get('category') ?? '');
        $categoryLabel = $category !== ''
            ? $this->language->translateOption($category, 'category', 'ActivityOfferSlot')
            : (string) ($slot->get('name') ?? 'Turno');

        $start = $this->formatDateTime((string) ($slot->get('dateStart') ?? ''));
        $end = $this->formatDateTime((string) ($slot->get('dateEnd') ?? ''), true);
        $required = (int) ($slot->get('requiredCount') ?? 1);

        $place = trim(implode(', ', array_filter([
            (string) ($slot->get('placeStreet') ?? ''),
            (string) ($slot->get('placeCity') ?? ''),
        ])));

        $line = $categoryLabel;

        if ($start !== '') {
            $line .= ' — ' . $start;
            if ($end !== '') {
                $line .= ' → ' . $end;
            }
        }

        $line .= ' · ' . $required . ' ' . ($required === 1 ? 'posto' : 'posti');

        if ($place !== '') {
            $line .= ' · ' . $place;
        }

        return $line;
    }

    private function formatDateTime(string $value, bool $timeOnlyIfSameDay = false): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($value);

            if ($timeOnlyIfSameDay) {
                return $dt->format('H:i');
            }

            return $dt->format('d/m/Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * @param string[] $lines
     */
    private function linesToHtml(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        return '<ul><li>' . implode('</li><li>', array_map(
            static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
            $lines
        )) . '</li></ul>';
    }

    private function getPrimaryEmailAddress(User $user): string
    {
        /** @var \Espo\Repositories\EmailAddress $repo */
        $repo = $this->entityManager->getRepository('EmailAddress');

        foreach ($repo->getEmailAddressData($user) as $item) {
            $address = trim((string) (is_object($item)
                ? ($item->emailAddress ?? '')
                : ($item['emailAddress'] ?? '')));

            if ($address !== '' && empty($item->invalid)) {
                return $address;
            }
        }

        return trim((string) ($user->get('emailAddress') ?? ''));
    }

    private function getTemplate(string $kind): ?EmailTemplate
    {
        $ids = $this->config->get(self::CONFIG_KEY);
        $ids = $ids ? json_decode(json_encode($ids), true) : [];

        $id = is_array($ids) ? ($ids[$kind] ?? null) : null;

        if (!$id) {
            return null;
        }

        /** @var ?EmailTemplate */
        return $this->entityManager->getEntityById(EmailTemplate::ENTITY_TYPE, $id);
    }
}
