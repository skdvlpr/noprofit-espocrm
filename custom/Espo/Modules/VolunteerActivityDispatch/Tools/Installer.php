<?php

namespace Espo\Modules\VolunteerActivityDispatch\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

/**
 * Post-install: ensure ActivityOffer tab + rebuild metadata/cache.
 */
class Installer
{
    public function runPostInstall(Container $container): void
    {
        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        /** @var Config $config */
        $config = $container->getByClass(Config::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $config->get('tabList', []) ?? [];
        $tabList = array_values(array_filter(
            $tabList,
            static fn ($item): bool => $item !== 'ActivityOffer'
        ));

        $taskPos = array_search('Task', $tabList, true);

        if ($taskPos === false) {
            $tabList[] = 'ActivityOffer';
        } else {
            array_splice($tabList, $taskPos + 1, 0, ['ActivityOffer']);
        }

        $configWriter->set('tabList', $tabList);
        $configWriter->save();

        $this->migrateLegacyPlaceVarchar($container);

        /** @var DataManager $dataManager */
        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();

        // After rebuild: new columns (activity_offer_slot_id) exist.
        $this->migrateShiftPlanningStatuses($container);
        $this->ensureUserCompetencesLayout($container, $injectableFactory);
        $this->ensureRoleAccess($container);
        $this->ensureEmailTemplates($container);
    }

    /**
     * Admin-editable email templates for volunteer emails. Created once;
     * IDs are kept in config (`vadEmailTemplateIds`) so admins may edit
     * content freely in the Email Templates UI without breaking lookup.
     */
    public function ensureEmailTemplates(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);
            /** @var Config $config */
            $config = $container->getByClass(Config::class);
            /** @var InjectableFactory $injectableFactory */
            $injectableFactory = $container->getByClass(InjectableFactory::class);

            $ids = $config->get(ShiftEmailService::CONFIG_KEY);
            $ids = $ids ? json_decode(json_encode($ids), true) : [];

            if (!is_array($ids)) {
                $ids = [];
            }

            $defs = [
                ShiftEmailService::KIND_AVAILABILITY_REQUEST => [
                    'name' => 'Turni — Richiesta disponibilità',
                    'subject' => 'Nuova pianificazione turni: {ActivityOffer.name}',
                    'body' => '<p>Ciao {User.firstName},</p>'
                        . '<p>è aperta la raccolta disponibilità per la pianificazione turni '
                        . '<strong>{ActivityOffer.name}</strong> (settimana dal {ActivityOffer.weekStart}).</p>'
                        . '<p>Indica la tua disponibilità aprendo il piano nel CRM:<br>'
                        . '<a href="{planUrl}">{planUrl}</a></p>'
                        . '<p>Grazie!</p>',
                ],
                ShiftEmailService::KIND_SHIFTS_CONFIRMED => [
                    'name' => 'Turni — Conferma turni',
                    'subject' => 'Turni confermati: {ActivityOffer.name}',
                    'body' => '<p>Ciao {User.firstName},</p>'
                        . '<p>i tuoi turni per <strong>{ActivityOffer.name}</strong> sono stati confermati:</p>'
                        . '<p>{shiftList}</p>'
                        . '<p>Dettagli nel CRM: <a href="{planUrl}">{planUrl}</a></p>',
                ],
            ];

            $changed = false;

            foreach ($defs as $kind => $def) {
                $existingId = $ids[$kind] ?? null;

                if ($existingId && $em->getEntityById('EmailTemplate', $existingId)) {
                    continue;
                }

                $template = $em->getRDBRepository('EmailTemplate')
                    ->where(['name' => $def['name']])
                    ->findOne();

                if (!$template) {
                    $template = $em->getNewEntity('EmailTemplate');
                    $template->set([
                        'name' => $def['name'],
                        'subject' => $def['subject'],
                        'body' => $def['body'],
                        'isHtml' => true,
                        'oneOff' => false,
                    ]);
                    $em->saveEntity($template, ['skipAll' => true, 'silent' => true]);
                }

                $ids[$kind] = $template->getId();
                $changed = true;
            }

            if ($changed) {
                $configWriter = $injectableFactory->create(ConfigWriter::class);
                $configWriter->set(ShiftEmailService::CONFIG_KEY, $ids);
                $configWriter->save();
            }
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'VolunteerActivityDispatch email template provisioning skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Scope-level access for this module's entities per canonical role.
     * Only these three scope keys are (re)written; everything else in the
     * role is left untouched. Applied additively and idempotently.
     */
    private const ROLE_SCOPE_DATA = [
        'Admin' => [
            'ActivityOffer' => [
                'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all', 'stream' => 'all',
            ],
            'ActivityOfferSlot' => [
                'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all',
            ],
            'ActivityInvite' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'all', 'delete' => 'all',
            ],
        ],
        'Manager' => [
            'ActivityOffer' => [
                'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all', 'stream' => 'all',
            ],
            'ActivityOfferSlot' => [
                'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all',
            ],
            'ActivityInvite' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'all', 'delete' => 'no',
            ],
        ],
        'Employee' => [
            'ActivityOffer' => [
                'create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'own', 'stream' => 'all',
            ],
            'ActivityOfferSlot' => [
                'create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'own',
            ],
            'ActivityInvite' => [
                'create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no',
            ],
        ],
        // Volunteers: open plans from notifications, view shifts, respond to
        // own invites. Writing goes through cohort-gated service endpoints.
        'Volunteer' => [
            'ActivityOffer' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all',
            ],
            'ActivityOfferSlot' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no',
            ],
            'ActivityInvite' => [
                'create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no',
            ],
        ],
    ];

    public function ensureRoleAccess(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);

            $changed = false;

            foreach (self::ROLE_SCOPE_DATA as $roleName => $scopeData) {
                $role = $em->getRDBRepository('Role')
                    ->where(['name' => $roleName])
                    ->findOne();

                if (!$role) {
                    continue;
                }

                $data = $role->get('data');
                $data = $data ? json_decode(json_encode($data), true) : [];

                if (!is_array($data)) {
                    $data = [];
                }

                foreach ($scopeData as $scope => $access) {
                    if (($data[$scope] ?? null) === $access) {
                        continue;
                    }

                    $data[$scope] = $access;
                    $changed = true;
                }

                $role->set('data', $data);
                $em->saveEntity($role, ['skipAll' => true, 'silent' => true]);
            }

            if ($changed) {
                $container->getByClass(DataManager::class)->clearCache();
            }
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'VolunteerActivityDispatch role access provisioning skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * One-shot migration to the weekly shift-planning model (v0.3):
     * - ActivityOffer: Published -> Confirmed (tasks/invites were already created).
     * - ActivityInvite: Pending -> Assigned, Accepted -> Confirmed.
     * - Backfill invite.activityOfferSlot from the task the invite pointed to.
     */
    public function migrateShiftPlanningStatuses(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);

            $offers = $em->getRDBRepository('ActivityOffer')
                ->where(['status' => 'Published'])
                ->find();

            foreach ($offers as $offer) {
                $offer->set('status', 'Confirmed');
                $em->saveEntity($offer, ['skipAll' => true, 'silent' => true]);
            }

            $statusMap = [
                'Pending' => 'Assigned',
                'Accepted' => 'Confirmed',
            ];

            $invites = $em->getRDBRepository('ActivityInvite')
                ->where(['status' => array_keys($statusMap)])
                ->find();

            foreach ($invites as $invite) {
                $invite->set('status', $statusMap[$invite->get('status')]);
                $em->saveEntity($invite, ['skipAll' => true, 'silent' => true]);
            }

            $orphanInvites = $em->getRDBRepository('ActivityInvite')
                ->where([
                    'activityOfferSlotId' => null,
                    'taskId!=' => null,
                ])
                ->find();

            foreach ($orphanInvites as $invite) {
                $slot = $em->getRDBRepository('ActivityOfferSlot')
                    ->where(['taskId' => $invite->get('taskId')])
                    ->findOne();

                if (!$slot) {
                    continue;
                }

                $invite->set('activityOfferSlotId', $slot->getId());
                $em->saveEntity($invite, ['skipAll' => true, 'silent' => true]);
            }
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'VolunteerActivityDispatch status migration skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Add the activityCompetences field to the User detail layout (idempotent,
     * respects admin layout customizations).
     */
    public function ensureUserCompetencesLayout(
        Container $container,
        InjectableFactory $injectableFactory
    ): void {
        try {
            $layoutService = $injectableFactory->create(\Espo\Tools\Layout\Service::class);

            $layout = $layoutService->getOriginal('User', 'detail');

            if (is_string($layout)) {
                $layout = json_decode($layout);
            }

            if (!is_array($layout)) {
                return;
            }

            if (str_contains(json_encode($layout) ?: '', 'activityCompetences')) {
                return;
            }

            $layout[] = (object) [
                'label' => 'Volunteering',
                'rows' => [
                    [
                        (object) ['name' => 'activityCompetences'],
                        false,
                    ],
                ],
            ];

            $layoutService->update('User', 'detail', null, $layout);
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'VolunteerActivityDispatch User layout provisioning skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * One-shot: old `place` varchar → `place_city` before address columns replace it.
     */
    private function migrateLegacyPlaceVarchar(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);
            $pdo = $em->getPDO();

            $hasPlace = (bool) $pdo->query(
                "SHOW COLUMNS FROM `activity_offer_slot` LIKE 'place'"
            )->fetch();

            if (!$hasPlace) {
                return;
            }

            $hasCity = (bool) $pdo->query(
                "SHOW COLUMNS FROM `activity_offer_slot` LIKE 'place_city'"
            )->fetch();

            if ($hasCity) {
                $pdo->exec(
                    "UPDATE `activity_offer_slot`
                     SET `place_city` = `place`
                     WHERE (`place_city` IS NULL OR `place_city` = '')
                       AND `place` IS NOT NULL AND `place` <> ''"
                );
            }
        } catch (\Throwable) {
            // Table may not exist yet on first install — rebuild creates schema.
        }
    }
}
