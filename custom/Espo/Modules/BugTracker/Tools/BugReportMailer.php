<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Tools;

use Espo\Core\Mail\EmailSender;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\Email;
use Espo\Entities\EmailTemplate;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\EmailTemplate\Data as EmailTemplateData;
use Espo\Tools\EmailTemplate\Params as EmailTemplateParams;
use Espo\Tools\EmailTemplate\Processor as EmailTemplateProcessor;
use Throwable;

/**
 * Sends technician / reporter emails for BugReport lifecycle events.
 */
class BugReportMailer
{
    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private EmailSender $emailSender,
        private EmailTemplateProcessor $emailTemplateProcessor,
        private Log $log,
    ) {}

    public function notifyTechnicianNewReport(Entity $bugReport): void
    {
        $to = $this->resolveTechnicianEmail();

        if ($to === null) {
            return;
        }

        $templateId = $this->config->get('bugTrackerNotifyEmailTemplateId');

        if (!is_string($templateId) || $templateId === '') {
            $this->sendPlain(
                $to,
                'New bug report: ' . (string) $bugReport->get('name'),
                $this->buildPlainNewBody($bugReport)
            );

            return;
        }

        $this->sendFromTemplate($templateId, $bugReport, $to);
    }

    public function notifyReporterClosed(Entity $bugReport): void
    {
        $createdById = $bugReport->get('createdById');

        if (!is_string($createdById) || $createdById === '') {
            return;
        }

        /** @var ?User $reporter */
        $reporter = $this->entityManager->getEntityById(User::ENTITY_TYPE, $createdById);

        if (!$reporter) {
            return;
        }

        $to = $reporter->getEmailAddress();

        if (!$to) {
            return;
        }

        $templateId = $this->config->get('bugTrackerClosedEmailTemplateId');

        if (!is_string($templateId) || $templateId === '') {
            $this->sendPlain(
                $to,
                'Bug closed: ' . (string) $bugReport->get('name'),
                $this->buildPlainClosedBody($bugReport)
            );

            return;
        }

        $this->sendFromTemplate($templateId, $bugReport, $to);
    }

    private function resolveTechnicianEmail(): ?string
    {
        $email = $this->config->get('bugTrackerTechnicianEmail');

        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        $userId = $this->config->get('bugTrackerTechnicianUserId');

        if (!is_string($userId) || $userId === '') {
            return null;
        }

        /** @var ?User $user */
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if (!$user) {
            return null;
        }

        $address = $user->getEmailAddress();

        return $address ?: null;
    }

    private function sendFromTemplate(string $templateId, Entity $bugReport, string $to): void
    {
        try {
            /** @var ?EmailTemplate $template */
            $template = $this->entityManager->getEntityById(EmailTemplate::ENTITY_TYPE, $templateId);

            if (!$template) {
                $this->log->warning("BugTracker: EmailTemplate {$templateId} not found.");

                return;
            }

            $emailData = $this->emailTemplateProcessor->process(
                $template,
                EmailTemplateParams::create()->withApplyAcl(false),
                EmailTemplateData::create()
                    ->withParent($bugReport)
                    ->withEntityHash([
                        $bugReport->getEntityType() => $bugReport,
                    ])
            );

            /** @var Email $email */
            $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);
            $email
                ->setSubject($emailData->getSubject())
                ->setBody($emailData->getBody())
                ->setIsHtml($emailData->isHtml())
                ->addToAddress($to);

            $this->emailSender
                ->create()
                ->withAddedHeader('Auto-Submitted', 'auto-generated')
                ->withAttachments($emailData->getAttachmentList())
                ->send($email);
        } catch (Throwable $e) {
            $this->log->warning('BugTracker: failed to send template email: ' . $e->getMessage());
        }
    }

    private function sendPlain(string $to, string $subject, string $body): void
    {
        try {
            /** @var Email $email */
            $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);
            $email
                ->setSubject($subject)
                ->setBody($body)
                ->setIsHtml(true)
                ->addToAddress($to);

            $this->emailSender
                ->create()
                ->withAddedHeader('Auto-Submitted', 'auto-generated')
                ->send($email);
        } catch (Throwable $e) {
            $this->log->warning('BugTracker: failed to send plain email: ' . $e->getMessage());
        }
    }

    private function buildPlainNewBody(Entity $bugReport): string
    {
        $name = htmlspecialchars((string) $bugReport->get('name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pageUrl = htmlspecialchars((string) $bugReport->get('pageUrl'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $description = nl2br(htmlspecialchars((string) $bugReport->get('description'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        return "<p>A new bug report was submitted.</p>"
            . "<p><strong>{$name}</strong></p>"
            . "<p>Page: <a href=\"{$pageUrl}\">{$pageUrl}</a></p>"
            . "<p>{$description}</p>";
    }

    private function buildPlainClosedBody(Entity $bugReport): string
    {
        $name = htmlspecialchars((string) $bugReport->get('name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<p>Your bug report <strong>{$name}</strong> has been closed.</p>"
            . "<p>Thank you for helping improve the CRM.</p>";
    }
}
