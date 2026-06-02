<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Metadata;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\Entities\Role;
use Espo\ORM\EntityManager;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Removes the legacy `GoogleSafehouse` row if present (previously shipped
 *   inside SafehouseCrm before this extension was split out).
 * - Rebuilds metadata so the integration definition is merged immediately.
 */
class Installer
{
    /** Must match {@see Resources/metadata/integrations/GoogleCalendarDrive.json} basename. */
    public const INTEGRATION_ID = 'GoogleCalendarDrive';

    /** Legacy id from an earlier SafehouseCrm-bundled draft. */
    private const LEGACY_SAFEHOUSE_GOOGLE_ID = 'GoogleSafehouse';
    /** Previous integration id before rename. */
    private const LEGACY_GOOGLE_INTEGRATION_ID = 'GoogleIntegration';

    /** @var array<int, array<string, mixed>> */
    private const DEFAULT_DATE_SOURCES = [
        [
            'name' => 'Meeting start date',
            'targetEntityType' => 'Meeting',
            'dateField' => 'dateStart',
            'endDateField' => 'dateEnd',
            'sourceDateType' => 'main',
            'label' => 'Meeting date',
            'allDay' => false,
            'sortOrder' => 10,
        ],
        [
            'name' => 'Call start date',
            'targetEntityType' => 'Call',
            'dateField' => 'dateStart',
            'endDateField' => 'dateEnd',
            'sourceDateType' => 'main',
            'label' => 'Call date',
            'allDay' => false,
            'sortOrder' => 20,
        ],
        [
            'name' => 'Task due date',
            'targetEntityType' => 'Task',
            'dateField' => 'dateEnd',
            'endDateField' => null,
            'sourceDateType' => 'main',
            'label' => 'Due date',
            'allDay' => true,
            'sortOrder' => 30,
        ],
        [
            'name' => 'Opportunity presentation date',
            'targetEntityType' => 'Opportunity',
            'dateField' => 'presentationDate',
            'endDateField' => null,
            'sourceDateType' => 'presentationDate',
            'label' => 'Presentation date',
            'allDay' => true,
            'sortOrder' => 40,
        ],
        [
            'name' => 'Opportunity close date',
            'targetEntityType' => 'Opportunity',
            'dateField' => 'closeDate',
            'endDateField' => null,
            'sourceDateType' => 'closeDate',
            'label' => 'Close date',
            'allDay' => true,
            'sortOrder' => 50,
        ],
        [
            'name' => 'Volunteer / Employee start date',
            'targetEntityType' => 'VolunteerEmployee',
            'dateField' => 'startDate',
            'endDateField' => null,
            'sourceDateType' => 'main',
            'label' => 'Start date',
            'allDay' => true,
            'sortOrder' => 60,
        ],
        [
            'name' => 'Volunteer / Employee end date',
            'targetEntityType' => 'VolunteerEmployee',
            'dateField' => 'endDate',
            'endDateField' => null,
            'sourceDateType' => 'endDate',
            'label' => 'End date',
            'allDay' => true,
            'sortOrder' => 61,
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const DEFAULT_CALENDAR_TEMPLATES = [
        [
            'name' => 'Meeting — default',
            'targetEntityType' => 'Meeting',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}}\n\n{{description}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
        ],
        [
            'name' => 'Call — default',
            'targetEntityType' => 'Call',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}}\n\n{{description}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
        ],
        [
            'name' => 'Task — default',
            'targetEntityType' => 'Task',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}}\n\n{{description}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
        ],
        [
            'name' => 'Opportunity — default',
            'targetEntityType' => 'Opportunity',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}} | {{account.name}}\n\n{{description}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
            'colorId' => '10',
        ],
        [
            'name' => 'Volunteer / Employee — default',
            'targetEntityType' => 'VolunteerEmployee',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}}\nStart: {{startDate}}\nEnd: {{endDate}}\n\n{{extra}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
        ],
    ];

    public function runPostInstall(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);
        $this->migrateLegacyIntegrationState($container, $em);
        $this->removeLegacyIntegrationRow($em);
        $this->ensureIntegrationRow($em);

        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();

        $this->ensureDefaultDateSources($em);
        $this->ensureDefaultCalendarTemplates($em);

        $container->getByClass(InjectableFactory::class)
            ->create(GoogleCalendarLayoutProvisioner::class)
            ->provisionAll();

        $metadata = $container->getByClass(Metadata::class);
        $this->ensureAdminRoleAccess($em, $metadata);
        $this->ensureNavigationTabs($container);

        $dataManager->rebuild();
    }

    private function migrateLegacyIntegrationState(Container $container, EntityManager $entityManager): void
    {
        $legacyIntegration = $this->findLegacyIntegrationWithState($entityManager);

        if ($legacyIntegration !== null) {
            $this->migrateIntegrationRow($entityManager, $legacyIntegration);
        }

        $this->migrateIntegrationConfigFlag($container, $legacyIntegration);
        $this->migrateLegacyExternalAccounts($entityManager);
    }

    private function findLegacyIntegrationWithState(EntityManager $entityManager): ?IntegrationEntity
    {
        $selected = null;

        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $id) {
            $legacy = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, $id);

            if (!$legacy instanceof IntegrationEntity) {
                continue;
            }

            if ($selected === null) {
                $selected = $legacy;

                continue;
            }

            if ($legacy->get('enabled') && !$selected->get('enabled')) {
                $selected = $legacy;

                continue;
            }

            if (
                !$this->isDataEmpty($legacy->get('data'))
                && $this->isDataEmpty($selected->get('data'))
            ) {
                $selected = $legacy;
            }
        }

        return $selected;
    }

    private function migrateIntegrationRow(EntityManager $entityManager, IntegrationEntity $legacy): void
    {
        $target = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);
        $isNew = false;

        if (!$target instanceof IntegrationEntity) {
            $target = $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, [
                'id' => self::INTEGRATION_ID,
                'enabled' => false,
            ]);
            $isNew = true;
        }

        $changed = $isNew;

        if ($this->isDataEmpty($target->get('data')) && !$this->isDataEmpty($legacy->get('data'))) {
            $target->set('data', $this->cloneData($legacy->get('data')));
            $changed = true;
        }

        if ($legacy->get('enabled') && !$target->get('enabled')) {
            $target->set('enabled', true);
            $changed = true;
        }

        if ($changed) {
            $entityManager->saveEntity($target);
        }
    }

    private function migrateIntegrationConfigFlag(Container $container, ?IntegrationEntity $legacyIntegration): void
    {
        $config = $container->getByClass(Config::class);
        $raw = $config->get('integrations');
        $wasObject = is_object($raw);
        $integrations = $wasObject ? get_object_vars($raw) : (is_array($raw) ? $raw : []);
        $changed = false;

        $legacyEnabled = $legacyIntegration !== null && (bool) $legacyIntegration->get('enabled');

        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $id) {
            if (!empty($integrations[$id])) {
                $legacyEnabled = true;
            }

            if (array_key_exists($id, $integrations)) {
                unset($integrations[$id]);
                $changed = true;
            }
        }

        if ($legacyEnabled && empty($integrations[self::INTEGRATION_ID])) {
            $integrations[self::INTEGRATION_ID] = true;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        $configWriter->set('integrations', $wasObject ? (object) $integrations : $integrations);
        $configWriter->save();
    }

    private function migrateLegacyExternalAccounts(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository('ExternalAccount');

        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $legacyId) {
            $prefix = $legacyId . '__';

            foreach ($repo->where(['id*' => $prefix . '%', 'deleted' => false])->find() as $legacy) {
                $legacyExternalAccountId = (string) $legacy->getId();
                $userId = substr($legacyExternalAccountId, strlen($prefix));

                if ($userId === '') {
                    continue;
                }

                $targetId = self::INTEGRATION_ID . '__' . $userId;
                $target = $entityManager->getEntityById('ExternalAccount', $targetId);
                $isNew = false;

                if ($target === null) {
                    $target = $entityManager->createEntity('ExternalAccount', [
                        'id' => $targetId,
                        'enabled' => false,
                    ]);
                    $isNew = true;
                }

                $changed = $isNew;

                if ($this->isDataEmpty($target->get('data')) && !$this->isDataEmpty($legacy->get('data'))) {
                    $target->set('data', $this->cloneData($legacy->get('data')));
                    $changed = true;
                }

                if ($legacy->get('enabled') && !$target->get('enabled')) {
                    $target->set('enabled', true);
                    $changed = true;
                }

                if ($legacy->get('isLocked') && !$target->get('isLocked')) {
                    $target->set('isLocked', true);
                    $changed = true;
                }

                if ($changed) {
                    $entityManager->saveEntity($target);
                }
            }
        }
    }

    private function isDataEmpty(mixed $data): bool
    {
        if ($data === null) {
            return true;
        }

        if (is_array($data)) {
            return $data === [];
        }

        if (is_object($data)) {
            return get_object_vars($data) === [];
        }

        return false;
    }

    private function cloneData(mixed $data): mixed
    {
        $encoded = json_encode($data);

        if ($encoded === false) {
            return $data;
        }

        return json_decode($encoded);
    }

    private function removeLegacyIntegrationRow(EntityManager $entityManager): void
    {
        $legacyList = $entityManager
            ->getRDBRepository(IntegrationEntity::ENTITY_TYPE)
            ->where([
                'OR' => [
                    ['id' => self::LEGACY_SAFEHOUSE_GOOGLE_ID],
                    ['id' => self::LEGACY_GOOGLE_INTEGRATION_ID],
                ],
            ])
            ->find();

        if ($legacyList === []) {
            return;
        }

        foreach ($legacyList as $legacy) {
            $entityManager->removeEntity($legacy);
        }
    }

    private function ensureIntegrationRow(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository(IntegrationEntity::ENTITY_TYPE);
        $existing = $repo
            ->select(['id'])
            ->where(['id' => self::INTEGRATION_ID])
            ->findOne();

        if ($existing !== null) {
            return;
        }

        $entity = $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, [
            'id' => self::INTEGRATION_ID,
            'enabled' => false,
        ]);
        $entityManager->saveEntity($entity);
    }

    private function ensureDefaultDateSources(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository('CalendarDateSource');

        foreach (self::DEFAULT_DATE_SOURCES as $source) {
            $existing = $repo
                ->where([
                    'targetEntityType' => $source['targetEntityType'],
                    'sourceDateType' => $source['sourceDateType'],
                    'deleted' => false,
                ])
                ->findOne();

            if ($existing !== null) {
                continue;
            }

            $entityManager->saveEntity($entityManager->createEntity('CalendarDateSource', array_merge([
                'isActive' => true,
                'calendarViewEnabled' => true,
            ], $source)));
        }
    }

    private function ensureDefaultCalendarTemplates(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository('CalendarTemplate');

        foreach (self::DEFAULT_CALENDAR_TEMPLATES as $template) {
            $existing = $repo
                ->where([
                    'targetEntityType' => $template['targetEntityType'],
                    'name' => $template['name'],
                    'deleted' => false,
                ])
                ->findOne();

            if ($existing !== null) {
                continue;
            }

            $entityManager->saveEntity($entityManager->createEntity('CalendarTemplate', array_merge([
                'isActive' => true,
            ], $template)));
        }
    }

    private function ensureNavigationTabs(Container $container): void
    {
        $config = $container->get('config');
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(\Espo\Core\Utils\Config\ConfigWriter::class);

        $tabList = $config->get('tabList', []) ?? [];

        foreach (['CalendarTemplate', 'CalendarDateSource'] as $tab) {
            if (!in_array($tab, $tabList, true)) {
                $tabList[] = $tab;
            }
        }

        $configWriter->set('tabList', $tabList);
        $configWriter->save();
    }

    private function ensureAdminRoleAccess(EntityManager $entityManager, Metadata $metadata): void
    {
        $role = $entityManager
            ->getRDBRepositoryByClass(Role::class)
            ->where(['name' => 'Admin', 'deleted' => false])
            ->findOne();

        if ($role === null) {
            return;
        }

        $data = json_decode(json_encode($role->get('data') ?? new \stdClass()), true) ?? [];
        $changed = false;

        foreach (['CalendarTemplate', 'CalendarDateSource'] as $entityType) {
            $expected = [];
            foreach (['read', 'create', 'edit', 'delete', 'stream'] as $action) {
                $value = $metadata->get(['aclDefs', $entityType, $action]);

                if (is_string($value) && $value !== '') {
                    $expected[$action] = $value;
                }
            }

            if ($expected === []) {
                continue;
            }

            if (($data[$entityType] ?? null) !== $expected) {
                $data[$entityType] = $expected;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $role->set('data', (object) $data);
        $entityManager->saveEntity($role);
    }
}
