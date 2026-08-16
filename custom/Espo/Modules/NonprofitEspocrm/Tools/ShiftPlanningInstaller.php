<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

/**
 * Shift-planning provisioning (tab, email templates, migrations).
 * Called from NonprofitEspocrm Installer and ProvisionShiftPlanning rebuild action.
 * Does not create or mutate Roles — ACL is Administration → Roles only.
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
        $this->migrateSlotStatusesPublishedCovered($container);
        $this->normalizeInviteOfferLinks($container);
        $this->ensureUserCompetencesLayout($container, $injectableFactory);
        $this->ensureEmailTemplates($container);
        $this->ensureCompletePastSlotsScheduling($container);
        $this->ensureReconcileFullyStaffedScheduling($container);
        $this->ensureActivityOfferSlotNativeCalendar($container, $injectableFactory);
        $this->ensureSlotCrmCalendarDateSource($container, $injectableFactory);
    }

    /**
     * Keep CompletePastActivityOfferSlots cron every 10 minutes on existing installs
     * (metadata alone does not rewrite ScheduledJob.scheduling).
     */
    private function ensureCompletePastSlotsScheduling(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);
            $jobName = 'SafehouseCrmCompletePastActivityOfferSlots';
            $wanted = '*/' . '10 * * * *';

            $job = $em->getRDBRepository('ScheduledJob')
                ->where(['job' => $jobName])
                ->findOne();

            if (!$job) {
                return;
            }

            if ((string) $job->get('scheduling') === $wanted) {
                return;
            }

            $job->set('scheduling', $wanted);
            $em->saveEntity($job, ['skipAll' => true, 'silent' => true]);
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'CompletePast slots scheduling update skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Keep ReconcileFullyStaffedPlans at every 5 minutes on existing installs.
     */
    private function ensureReconcileFullyStaffedScheduling(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);
            $jobName = 'SafehouseCrmReconcileFullyStaffedPlans';
            $wanted = '*/' . '5 * * * *';

            $job = $em->getRDBRepository('ScheduledJob')
                ->where(['job' => $jobName])
                ->findOne();

            if (!$job) {
                $job = $em->getNewEntity('ScheduledJob');
                $job->set([
                    'name' => 'Reconcile shift-plan availability coverage',
                    'job' => $jobName,
                    'status' => 'Active',
                    'scheduling' => $wanted,
                ]);
                $em->saveEntity($job, ['skipAll' => true, 'silent' => true]);

                return;
            }

            if ((string) $job->get('scheduling') === $wanted) {
                return;
            }

            $job->set('scheduling', $wanted);
            $em->saveEntity($job, ['skipAll' => true, 'silent' => true]);
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'Reconcile fully-staffed scheduling update skipped: ' . $e->getMessage()
            );
        }
    }

    /**
     * Volunteer shifts on Espo CRM calendar via native calendarEntityList + scopes.calendar.
     * Does not require GoogleIntegration.
     */
    private function ensureActivityOfferSlotNativeCalendar(
        Container $container,
        InjectableFactory $injectableFactory
    ): void {
        /** @var Config $config */
        $config = $container->getByClass(Config::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $list = $config->get('calendarEntityList') ?? [];
        if (!is_array($list)) {
            $list = [];
        }

        $list = array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? $v : null, $list),
            static fn ($v) => $v !== null && $v !== ''
        ));

        if (in_array('ActivityOfferSlot', $list, true)) {
            return;
        }

        $list[] = 'ActivityOfferSlot';
        $configWriter->set('calendarEntityList', $list);
        $configWriter->save();
    }

    /**
     * Optional Google export CDS for ActivityOfferSlot (calendarViewEnabled=false).
     * CRM calendar display uses native Espo — see ensureActivityOfferSlotNativeCalendar.
     */
    private function ensureSlotCrmCalendarDateSource(
        Container $container,
        InjectableFactory $injectableFactory
    ): void {
        if (!class_exists(\Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner::class)) {
            return;
        }

        if (!class_exists(\Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseGoogleCalendarProvisioner::class)) {
            return;
        }

        $injectableFactory
            ->create(\Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseGoogleCalendarProvisioner::class)
            ->ensureDateSourcesOnly($container);
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
            $contentVersion = '2026-08-08-fully-staffed-v3';
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
        // logoHtml already includes logo + brand name — do not repeat {logoHtml}/{brandName}.
        $signOff = '<p>Grazie!<br><strong>' . $brand . '</strong></p>';

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
                    . '<p>Solo dopo questa email i turni risultano ufficialmente assegnati a te.</p>'
                    . '<p><strong>I tuoi turni</strong> (dove / quando / condizioni):</p>{shiftList}'
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
            ShiftEmailService::KIND_PLAN_UPDATED => [
                'name' => 'Turni — Aggiornamento piano',
                'subject' => 'Piano turni aggiornato: {ActivityOffer.name}',
                'body' => '{logoHtml}'
                    . '<p>Ciao <strong>{User.firstName}</strong>,</p>'
                    . '<p>il piano turni <strong>{ActivityOffer.name}</strong> '
                    . '(settimana dal {ActivityOffer.weekStart}) è stato <strong>aggiornato</strong>.</p>'
                    . '<p><strong>Cosa è cambiato:</strong></p>{changeList}'
                    . '<p><strong>Turni previsti:</strong></p>{slotList}'
                    . '<p>Controlla i dettagli e aggiorna la disponibilità se necessario:</p>'
                    . '<p><a href="{recordUrl}"><strong>Apri la pianificazione turni</strong></a><br>'
                    . '<span style="font-size:12px;color:#888">{recordUrl}</span></p>'
                    . $signOff,
            ],
            ShiftEmailService::KIND_SHIFT_CANCELLED => [
                'name' => 'Turni — Turno annullato',
                'subject' => 'Turno annullato: {ActivityOffer.name}',
                'body' => '{logoHtml}'
                    . '<p>Ciao <strong>{User.firstName}</strong>,</p>'
                    . '<p>uno o più turni del piano <strong>{ActivityOffer.name}</strong> '
                    . '(settimana dal {ActivityOffer.weekStart}) sono stati <strong>annullati</strong>.</p>'
                    . '<p><strong>Turni annullati:</strong></p>{shiftList}'
                    . '<p>Non è più necessario presentarti a questi turni. '
                    . 'Per dettagli apri il piano nel CRM:</p>'
                    . '<p><a href="{recordUrl}"><strong>Apri la pianificazione turni</strong></a><br>'
                    . '<span style="font-size:12px;color:#888">{recordUrl}</span></p>'
                    . $signOff,
            ],
            ShiftEmailService::KIND_WEEK_FULLY_STAFFED => [
                'name' => 'Turni — Disponibilità sufficienti',
                'subject' => 'Disponibilità sufficienti: {ActivityOffer.name}',
                'body' => '{logoHtml}'
                    . '<p>Ciao <strong>{User.firstName}</strong>,</p>'
                    . '<p>per il piano turni <strong>{ActivityOffer.name}</strong> '
                    . '(settimana dal {ActivityOffer.weekStart}) ci sono ora '
                    . '<strong>abbastanza volontari che hanno indicato la propria disponibilità</strong> '
                    . 'per tutti i turni attivi.</p>'
                    . '<p><strong>Turni previsti:</strong></p>{slotList}'
                    . '<p>Puoi assegnare e confermare i turni manualmente, oppure — se hai attivato '
                    . 'l’opzione <strong>assegna e conferma automaticamente</strong> — '
                    . 'il sistema assegna le persone e invia le email di conferma da solo.</p>'
                    . '<p><a href="{recordUrl}"><strong>Apri la pianificazione turni</strong></a><br>'
                    . '<span style="font-size:12px;color:#888">{recordUrl}</span></p>'
                    . $signOff,
            ],
        ];
    }

    /**
     * Drop Draft/Cancelled from ActivityOfferSlot: map legacy values to Published.
     * Plan-level ActivityOffer.Draft is unchanged.
     */
    public function migrateSlotStatusesPublishedCovered(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);

            // Raw SQL: after Draft is removed from entityDefs enum options, ORM
            // where/find may miss or refuse legacy values still stored in DB.
            $pdo = $em->getPDO();
            $pdo->exec(
                "UPDATE activity_offer_slot"
                . " SET status = 'Published'"
                . " WHERE status IN ('Draft', 'Cancelled')"
            );
        } catch (\Throwable $e) {
            $container->getByClass(\Espo\Core\Utils\Log::class)->warning(
                'NonprofitEspocrm slot status migration skipped: ' . $e->getMessage()
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
