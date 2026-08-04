<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

/**
 * Shift-planning provisioning (tab, roles, email templates, migrations).
 * Called from NonprofitEspocrm Installer and ProvisionShiftPlanning rebuild action.
 */
class ShiftPlanningInstaller
{
    /**
     * Insert ActivityOffer after Task in navbar (idempotent).
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    public function ensureActivityOfferTab(array $tabList): array
    {
        $tabList = array_values(array_filter(
            $tabList,
            static fn ($item): bool => !in_array($item, ['ActivityOffer', 'ActivityOfferSlot'], true)
        ));

        $taskPos = array_search('Task', $tabList, true);
        $insert = ['ActivityOffer', 'ActivityOfferSlot'];

        if ($taskPos === false) {
            array_push($tabList, ...$insert);
        } else {
            array_splice($tabList, $taskPos + 1, 0, $insert);
        }

        return $tabList;
    }

    /**
     * Full post-install path (standalone). Prefer NonprofitEspocrm Installer
     * which calls ensureActivityOfferTab + postRebuildProvision around one rebuild.
     */
    public function runPostInstall(Container $container): void
    {
        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        /** @var Config $config */
        $config = $container->getByClass(Config::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $this->ensureActivityOfferTab($config->get('tabList', []) ?? []);
        $configWriter->set('tabList', $tabList);
        $configWriter->save();

        $this->migrateLegacyPlaceVarchar($container);

        /** @var DataManager $dataManager */
        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();

        $this->postRebuildProvision($container, $injectableFactory);
    }

    public function postRebuildProvision(Container $container, InjectableFactory $injectableFactory): void
    {
        /** @var Config $config */
        $config = $container->getByClass(Config::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $this->ensureActivityOfferTab($config->get('tabList', []) ?? []);
        $configWriter->set('tabList', $tabList);
        $configWriter->save();

        $this->migrateShiftPlanningStatuses($container);
        $this->normalizeInviteOfferLinks($container);
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

            $defs = $this->getShiftEmailTemplateDefs();
            $changed = false;
            // Bump when default subject/body must be rewritten on existing installs.
            $contentVersion = '2026-08-04-safehouse-weekly-v1';
            $storedVersion = (string) ($config->get('vadEmailTemplateContentVersion') ?? '');
            $refreshBodies = $storedVersion !== $contentVersion;

            foreach ($defs as $kind => $def) {
                $existingId = $ids[$kind] ?? null;
                $template = $existingId ? $em->getEntityById('EmailTemplate', $existingId) : null;

                if (!$template) {
                    $template = $em->getRDBRepository('EmailTemplate')
                        ->where(['name' => $def['name']])
                        ->findOne();
                }

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
                    $ids[$kind] = $template->getId();
                    $changed = true;

                    continue;
                }

                if ($refreshBodies || ($ids[$kind] ?? null) !== $template->getId()) {
                    $template->set([
                        'subject' => $def['subject'],
                        'body' => $def['body'],
                        'isHtml' => true,
                    ]);
                    $em->saveEntity($template, ['skipAll' => true, 'silent' => true]);
                    $ids[$kind] = $template->getId();
                    $changed = true;
                }
            }

            if ($changed || $refreshBodies) {
                $configWriter = $injectableFactory->create(ConfigWriter::class);
                $configWriter->set(ShiftEmailService::CONFIG_KEY, $ids);
                $configWriter->set('vadEmailTemplateContentVersion', $contentVersion);
                $configWriter->save();
            }
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'NonprofitEspocrm email template provisioning skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Default (Italian) bodies for shift-planning emails. Use {recordUrl}
     * for the plan deep-link; {planUrl} remains accepted as an alias at send time.
     *
     * @return array<string, array{name: string, subject: string, body: string}>
     */
    private function getShiftEmailTemplateDefs(): array
    {
        $brand = ShiftEmailService::BRAND_NAME;
        $signOff = '{logoHtml}<p>Grazie!<br><strong>{brandName}</strong></p>';

        return [
            ShiftEmailService::KIND_AVAILABILITY_REQUEST => [
                'name' => 'Turni — Richiesta disponibilità',
                'subject' => 'Disponibilità richieste: {ActivityOffer.name}',
                'body' => '{logoHtml}'
                    . '<p>Ciao <strong>{User.firstName}</strong>,</p>'
                    . '<p>è aperta la <strong>raccolta disponibilità</strong> per la pianificazione turni '
                    . '<strong>{ActivityOffer.name}</strong>.</p>'
                    . '<p><strong>Settimana dal:</strong> {ActivityOffer.weekStart}<br>'
                    . '<strong>Turni in piano:</strong> {slotCount}</p>'
                    . '<p>{ActivityOffer.description}</p>'
                    . '<p><strong>Turni previsti:</strong></p>{slotList}'
                    . '<p>Indica su quali turni sei disponibile (solo le categorie per cui sei abilitato '
                    . 'risultano selezionabili). Apri il piano nel CRM:</p>'
                    . '<p><a href="{recordUrl}"><strong>Apri la pianificazione turni</strong></a><br>'
                    . '<span style="font-size:12px;color:#888">{recordUrl}</span></p>'
                    . '<p>Grazie per il tuo aiuto!<br><strong>' . $brand . '</strong></p>',
            ],
            ShiftEmailService::KIND_SHIFTS_CONFIRMED => [
                'name' => 'Turni — Conferma turni',
                'subject' => 'Turni confermati: {ActivityOffer.name}',
                'body' => '{logoHtml}'
                    . '<p>Ciao <strong>{User.firstName}</strong>,</p>'
                    . '<p>i tuoi turni per <strong>{ActivityOffer.name}</strong> '
                    . '(settimana dal {ActivityOffer.weekStart}) sono stati <strong>confermati</strong>.</p>'
                    . '<p><strong>I tuoi turni</strong> (dove / quando / condizioni):</p>{shiftList}'
                    . '<p>Troverai le relative attività anche nella sezione <strong>Task</strong> del CRM.</p>'
                    . '<p>Se non puoi più presentarti, avvisa subito l’organizzazione e aggiorna la tua '
                    . 'disponibilità dal piano:</p>'
                    . '<p><a href="{recordUrl}"><strong>Apri la pianificazione turni</strong></a><br>'
                    . '<span style="font-size:12px;color:#888">{recordUrl}</span></p>'
                    . $signOff,
            ],
            ShiftEmailService::KIND_ADMIN_DIGEST => [
                'name' => 'Turni — Riepilogo conferma (admin)',
                'subject' => 'Piano turni confermato: {ActivityOffer.name}',
                'body' => '{logoHtml}'
                    . '<p>Ciao <strong>{User.firstName}</strong>,</p>'
                    . '<p>il piano turni <strong>{ActivityOffer.name}</strong> '
                    . '(settimana dal {ActivityOffer.weekStart}) è stato <strong>confermato</strong>.</p>'
                    . '<p><strong>Assegnazioni:</strong></p>{shiftList}'
                    . '<p><a href="{recordUrl}"><strong>Apri la pianificazione turni</strong></a><br>'
                    . '<span style="font-size:12px;color:#888">{recordUrl}</span></p>'
                    . $signOff,
            ],
        ];
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
                'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all',
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
        // Members: read-only visibility of shift plans (stream posts allowed).
        'Member' => [
            'ActivityOffer' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all',
            ],
            'ActivityOfferSlot' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no',
            ],
            'ActivityInvite' => [
                'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no',
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
                'NonprofitEspocrm role access provisioning skipped: ' . $e->getMessage()
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
                'NonprofitEspocrm status migration skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Align invite.activityOfferId with the plan its slot belongs to.
     * Slots can be re-parented to another plan after invites were created,
     * which left stale offer links and caused UNIQ_SLOT_USER duplicate-key
     * failures on saveAvailability.
     */
    public function normalizeInviteOfferLinks(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);

            $invites = $em->getRDBRepository('ActivityInvite')
                ->where(['activityOfferSlotId!=' => null])
                ->find();

            foreach ($invites as $invite) {
                $slot = $em->getEntityById('ActivityOfferSlot', $invite->get('activityOfferSlotId'));

                if (!$slot) {
                    continue;
                }

                $slotOfferId = $slot->get('activityOfferId');

                if ($slotOfferId && $invite->get('activityOfferId') !== $slotOfferId) {
                    $invite->set('activityOfferId', $slotOfferId);
                    $em->saveEntity($invite, ['skipAll' => true, 'silent' => true]);
                }
            }
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'NonprofitEspocrm invite link normalization skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Ensure User detail Volunteering panel has activityCompetences + isOccasional.
     * Uses LayoutManager (not Layout\Service) so CLI/prod rebuild works.
     */
    public function ensureUserCompetencesLayout(
        Container $container,
        InjectableFactory $injectableFactory
    ): void {
        try {
            /** @var \Espo\Tools\LayoutManager\LayoutManager $layoutManager */
            $layoutManager = $injectableFactory->create(
                \Espo\Tools\LayoutManager\LayoutManager::class
            );

            $raw = $layoutManager->get('User', 'detail');

            if ($raw === null || $raw === '') {
                throw new \RuntimeException('User detail layout is empty');
            }

            $hasFullPanels = str_contains($raw, 'volunteeringProfile')
                && str_contains($raw, 'memberProfile');

            if ($hasFullPanels) {
                return;
            }

            $hasCompetences = str_contains($raw, 'activityCompetences');
            $hasOccasional = str_contains($raw, 'isOccasional');

            if ($hasCompetences && $hasOccasional && !$hasFullPanels) {
                // Prefer NonprofitEspocrm ProvisionUserContactProfile for full panels.
                return;
            }

            $layout = json_decode($raw);

            if (!is_array($layout)) {
                throw new \RuntimeException('User detail layout JSON is not an array');
            }

            if (!$hasCompetences) {
                $layout[] = (object) [
                    'label' => 'Volunteering',
                    'rows' => [
                        [
                            (object) ['name' => 'activityCompetences'],
                            (object) ['name' => 'isOccasional'],
                        ],
                    ],
                ];
            } else {
                $layout = $this->injectIsOccasionalIntoUserLayout($layout);
            }

            $layoutManager->set($layout, 'User', 'detail');
            $layoutManager->save();

            /** @var \Espo\Core\DataManager $dataManager */
            $dataManager = $container->getByClass(DataManager::class);
            $dataManager->updateCacheTimestamp();
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'NonprofitEspocrm User layout provisioning skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param array<int, mixed> $layout
     * @return array<int, mixed>
     */
    private function injectIsOccasionalIntoUserLayout(array $layout): array
    {
        foreach ($layout as $panelIndex => $panel) {
            $panelObj = is_array($panel) ? (object) $panel : $panel;

            if (!is_object($panelObj) || !isset($panelObj->rows) || !is_array($panelObj->rows)) {
                continue;
            }

            $panelJson = json_encode($panelObj) ?: '';

            if (!str_contains($panelJson, 'activityCompetences')) {
                continue;
            }

            if (str_contains($panelJson, 'isOccasional')) {
                return $layout;
            }

            foreach ($panelObj->rows as $rowIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach ($row as $cellIndex => $cell) {
                    $name = is_object($cell) ? ($cell->name ?? null) : (is_array($cell) ? ($cell['name'] ?? null) : null);

                    if ($name !== 'activityCompetences') {
                        continue;
                    }

                    // Put isOccasional in the empty cell next to competences, or append a row.
                    if (count($row) === 1) {
                        $row[] = (object) ['name' => 'isOccasional'];
                    } elseif (isset($row[1]) && ($row[1] === false || $row[1] === null)) {
                        $row[1] = (object) ['name' => 'isOccasional'];
                    } else {
                        $panelObj->rows[] = [
                            (object) ['name' => 'isOccasional'],
                            false,
                        ];
                        $layout[$panelIndex] = $panelObj;

                        return $layout;
                    }

                    $panelObj->rows[$rowIndex] = $row;
                    $layout[$panelIndex] = $panelObj;

                    return $layout;
                }
            }
        }

        $layout[] = (object) [
            'label' => 'Volunteering',
            'rows' => [
                [
                    (object) ['name' => 'isOccasional'],
                    false,
                ],
            ],
        ];

        return $layout;
    }

    /**
     * One-shot: old `place` varchar → `place_city` before address columns replace it.
     */
    public function migrateLegacyPlaceVarchar(Container $container): void
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
