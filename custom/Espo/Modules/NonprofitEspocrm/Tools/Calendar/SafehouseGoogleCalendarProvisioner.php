<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Calendar;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;
use Espo\ORM\EntityManager;

/**
 * Seeds Safehouse CalendarDateSource rows, grant-oriented templates, and GCal layouts
 * when GoogleIntegration is installed alongside SafehouseCrm.
 */
class SafehouseGoogleCalendarProvisioner
{
    public function run(Container $container): void
    {
        if (!class_exists(GoogleCalendarLayoutProvisioner::class)) {
            return;
        }

        $em = $container->getByClass(EntityManager::class);
        $metadata = $container->getByClass(Metadata::class);
        $metadata->init(true);

        $this->ensureDateSources($em, $metadata);
        $this->ensureCalendarTemplates($em, $metadata);

        $container->getByClass(InjectableFactory::class)
            ->create(GoogleCalendarLayoutProvisioner::class)
            ->provisionAll();

        $container->getByClass(DataManager::class)->rebuild();
    }

    private function ensureDateSources(EntityManager $entityManager, Metadata $metadata): void
    {
        $repo = $entityManager->getRDBRepository('CalendarDateSource');

        foreach (SafehouseCalendarDateSourceDefaults::sources() as $source) {
            if (!$this->isSupportedDateSource($metadata, $source)) {
                continue;
            }

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

    private function ensureCalendarTemplates(EntityManager $entityManager, Metadata $metadata): void
    {
        $repo = $entityManager->getRDBRepository('CalendarTemplate');

        foreach (SafehouseCalendarDateSourceDefaults::DEFAULT_CALENDAR_TEMPLATES as $template) {
            $targetEntityType = $template['targetEntityType'] ?? null;

            if (
                !is_string($targetEntityType)
                || $targetEntityType === ''
                || !$metadata->get(['scopes', $targetEntityType, 'entity'])
            ) {
                continue;
            }

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

    /**
     * @param array<string, mixed> $source
     */
    private function isSupportedDateSource(Metadata $metadata, array $source): bool
    {
        $targetEntityType = $source['targetEntityType'] ?? null;

        if (!is_string($targetEntityType) || $targetEntityType === '') {
            return false;
        }

        if (!$metadata->get(['scopes', $targetEntityType, 'entity'])) {
            return false;
        }

        foreach (['dateField', 'endDateField'] as $key) {
            $field = $source[$key] ?? null;

            if (!is_string($field) || $field === '') {
                continue;
            }

            $fieldType = $metadata->get(['entityDefs', $targetEntityType, 'fields', $field, 'type']);

            if (!in_array($fieldType, ['date', 'datetime', 'datetimeOptional'], true)) {
                return false;
            }
        }

        return is_string($source['dateField'] ?? null) && $source['dateField'] !== '';
    }
}
