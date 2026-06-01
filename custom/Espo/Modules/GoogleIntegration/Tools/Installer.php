<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceEntityTypesReader;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DefaultCalendarTemplateProvisioner;
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
        $this->removeLegacyIntegrationRow($em);
        $this->ensureIntegrationRow($em);

        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();

        $this->ensureDefaultDateSources($em);
        $this->ensureDefaultCalendarTemplates($em);
        $this->ensureCalendarTemplatesForActiveDateSources($container, $em);

        $container->getByClass(InjectableFactory::class)
            ->create(GoogleCalendarLayoutProvisioner::class)
            ->provisionAll();

        $metadata = $container->getByClass(Metadata::class);
        $this->ensureAdminRoleAccess($em, $metadata);
        $this->pruneGoogleCalendarConfigFromNavigation($container);

        $dataManager->rebuild();
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

    private function ensureCalendarTemplatesForActiveDateSources(
        Container $container,
        EntityManager $entityManager
    ): void {
        $provisioner = $container->getByClass(InjectableFactory::class)
            ->create(DefaultCalendarTemplateProvisioner::class);

        foreach ((new DateSourceEntityTypesReader())->readActiveTargetEntityTypes() as $entityType) {
            $provisioner->ensureForEntityType($entityType);
        }

        (new DateSourceEntityTypesReader())->writeCacheFromDatabase();
    }

    /** @var list<string> */
    private const GOOGLE_CALENDAR_CONFIG_TABS = ['CalendarTemplate', 'CalendarDateSource'];

    public function pruneGoogleCalendarConfigFromNavigation(Container $container): void
    {
        $config = $container->get('config');
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(\Espo\Core\Utils\Config\ConfigWriter::class);

        $tabList = $this->pruneTabList($config->get('tabList', []) ?? []);
        $quickCreateList = array_values(array_filter(
            $config->get('quickCreateList', []) ?? [],
            static fn (mixed $item): bool => !is_string($item)
                || !in_array($item, self::GOOGLE_CALENDAR_CONFIG_TABS, true)
        ));

        $configWriter->set('tabList', $tabList);
        $configWriter->set('quickCreateList', $quickCreateList);
        $configWriter->save();
    }

    /**
     * @param list<mixed> $tabList
     * @return list<mixed>
     */
    private function pruneTabList(array $tabList): array
    {
        $result = [];

        foreach ($tabList as $item) {
            if (is_string($item)) {
                if (!in_array($item, self::GOOGLE_CALENDAR_CONFIG_TABS, true)) {
                    $result[] = $item;
                }

                continue;
            }

            if (is_array($item) && isset($item['itemList']) && is_array($item['itemList'])) {
                $item['itemList'] = $this->pruneTabList($item['itemList']);
            }

            $result[] = $item;
        }

        return $result;
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
