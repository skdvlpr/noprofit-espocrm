<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use RuntimeException;

class GoogleCalendarLayoutProvisioner
{
    private const MODULE = 'GoogleIntegration';

    public function __construct(
        private CapableEntityTypeResolver $resolver,
        private Metadata $metadata,
        private DateSourceEntityTypesReader $dateSourceEntityTypesReader
    ) {}

    public function provisionAll(): void
    {
        foreach ($this->resolver->getProvisionableEntityTypes() as $entityType) {
            $this->provisionEntityType($entityType);
        }

        $this->dateSourceEntityTypesReader->writeCacheFromDatabase();
    }

    public function provisionEntityType(string $entityType): void
    {
        $this->writeDetailLayout($entityType, 'detail');
        $this->writeDetailLayout($entityType, 'detailSmall');
        $this->writeI18nFieldLabels($entityType);
        $this->dateSourceEntityTypesReader->writeCacheFromDatabase();
    }

    private function writeDetailLayout(string $entityType, string $layoutName): void
    {
        $merged = $this->mergeGooglePanel($this->readBaseLayout($entityType, $layoutName));

        if ($this->countGoogleCalendarPanels($merged) !== 1) {
            throw new RuntimeException(
                'Google Calendar layout merge produced '
                . $this->countGoogleCalendarPanels($merged)
                . " panels for {$entityType}/{$layoutName} (expected 1)."
            );
        }

        foreach ($this->layoutWritePaths($entityType, $layoutName) as $path) {
            $this->writeLayoutFile($path, $merged);
        }
    }

    /**
     * Espo FileReader serves custom/Espo/Custom/Resources/layouts before module overrides.
     *
     * @return array<int, string>
     */
    private function layoutWritePaths(string $entityType, string $layoutName): array
    {
        $paths = [$this->layoutPath($entityType, $layoutName)];

        $customPath = $this->customResourcesLayoutPath($entityType, $layoutName);

        if ($customPath !== null) {
            $paths[] = $customPath;
        }

        return array_values(array_unique($paths));
    }

    private function customResourcesLayoutPath(string $entityType, string $layoutName): ?string
    {
        $path = 'custom/Espo/Custom/Resources/layouts/' . $entityType . '/' . $layoutName . '.json';

        return is_readable($path) ? $path : null;
    }

    /**
     * @param array<int, mixed> $layout
     */
    private function writeLayoutFile(string $path, array $layout): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create layout directory: ' . $dir);
        }

        file_put_contents($path, Json::encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    /**
     * @return array<int, mixed>
     */
    private function readBaseLayout(string $entityType, string $layoutName): array
    {
        $customPath = $this->customResourcesLayoutPath($entityType, $layoutName);

        if ($customPath !== null) {
            return $this->stripGoogleCalendarPanel($this->decodeLayoutFile($customPath));
        }

        // Never read GoogleIntegration layouts — they are output-only.
        foreach ($this->preferredBaseLayoutModules($entityType) as $moduleName) {
            $layout = $this->readLayoutFromModule($moduleName, $entityType, $layoutName);

            if ($layout !== null) {
                return $layout;
            }
        }

        $module = $this->metadata->get(['scopes', $entityType, 'module']);

        if (is_string($module) && $module !== '' && $module !== self::MODULE) {
            $layout = $this->readLayoutFromModule($module, $entityType, $layoutName);

            if ($layout !== null) {
                return $layout;
            }
        }

        foreach (array_reverse($this->metadata->getModuleList()) as $moduleName) {
            if (
                $moduleName === self::MODULE
                || $moduleName === 'Custom'
                || in_array($moduleName, $this->preferredBaseLayoutModules($entityType), true)
            ) {
                continue;
            }

            $layout = $this->readLayoutFromModule($moduleName, $entityType, $layoutName);

            if ($layout !== null) {
                return $layout;
            }
        }

        return [];
    }

    /**
     * @return array<int, mixed>|null
     */
    private function readLayoutFromModule(string $module, string $entityType, string $layoutName): ?array
    {
        foreach ([
            $this->moduleLayoutPath($module, $entityType, $layoutName),
            $this->applicationModuleLayoutPath($module, $entityType, $layoutName),
        ] as $path) {
            if (!is_readable($path)) {
                continue;
            }

            return $this->normalizeLayout($this->decodeLayoutFile($path));
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeLayoutFile(string $path): array
    {
        $decoded = Json::decode(file_get_contents($path) ?: '[]');

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Espo Json::decode may return stdClass panels; strip logic requires arrays.
     *
     * @param array<int, mixed> $layout
     * @return array<int, mixed>
     */
    private function normalizeLayout(array $layout): array
    {
        $normalized = [];

        foreach ($layout as $panel) {
            if (is_object($panel)) {
                $panel = json_decode(json_encode($panel), true);
            }

            if (!is_array($panel)) {
                continue;
            }

            if (isset($panel['rows']) && is_array($panel['rows'])) {
                foreach ($panel['rows'] as $rowIndex => $row) {
                    if (is_object($row)) {
                        $panel['rows'][$rowIndex] = json_decode(json_encode($row), true);
                    }
                }
            }

            $normalized[] = $panel;
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $layout
     * @return array<int, mixed>
     */
    private function stripGoogleCalendarPanel(array $layout): array
    {
        $layout = $this->normalizeLayout($layout);

        return array_values(array_filter(
            $layout,
            fn (array $panel): bool => !$this->panelContainsGoogleCalendarFields($panel)
        ));
    }

    /**
     * @param array<string, mixed> $panel
     */
    private function panelContainsGoogleCalendarFields(array $panel): bool
    {
        if (($panel['name'] ?? null) === 'GoogleCalendar') {
            return true;
        }

        if (($panel['label'] ?? null) === 'Google Calendar') {
            return true;
        }

        foreach ($panel['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $cell) {
                if (!is_array($cell)) {
                    continue;
                }

                $field = $cell['name'] ?? null;

                if (
                    is_string($field)
                    && (str_starts_with($field, 'googleCalendar') || $field === 'saveToGoogleCalendar')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $layout
     * @return array<int, mixed>
     */
    private function mergeGooglePanel(array $layout): array
    {
        $layout = $this->stripGoogleCalendarPanel($layout);

        $layout[] = [
            'name' => 'GoogleCalendar',
            'label' => 'Google Calendar',
            'rows' => GoogleCalendarCapableEntities::googleCalendarDetailPanelRows(),
        ];

        return $layout;
    }

    /**
     * @param array<int, mixed> $layout
     */
    private function countGoogleCalendarPanels(array $layout): int
    {
        $count = 0;

        foreach ($this->normalizeLayout($layout) as $panel) {
            if (($panel['name'] ?? null) === 'GoogleCalendar') {
                $count++;
            }
        }

        return $count;
    }

    private function writeI18nFieldLabels(string $entityType): void
    {
        foreach (GoogleCalendarCapableEntities::i18nBundleByLanguage() as $language => $bundle) {
            $path = 'custom/Espo/Modules/' . self::MODULE
                . '/Resources/i18n/' . $language . '/' . $entityType . '.json';

            $existing = [];

            if (is_readable($path)) {
                $decoded = Json::decode(file_get_contents($path) ?: '{}');

                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            $existing['fields'] = array_merge($existing['fields'] ?? [], $bundle['fields']);
            $existing['labels'] = array_merge($existing['labels'] ?? [], $bundle['labels']);
            $existing['options'] = array_merge($existing['options'] ?? [], $bundle['options']);

            $dir = dirname($path);

            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create i18n directory: ' . $dir);
            }

            file_put_contents(
                $path,
                Json::encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
            );
        }
    }

    private function layoutPath(string $entityType, string $layoutName): string
    {
        return $this->moduleLayoutPath(self::MODULE, $entityType, $layoutName);
    }

    private function moduleLayoutPath(string $module, string $entityType, string $layoutName): string
    {
        return 'custom/Espo/Modules/' . $module
            . '/Resources/layouts/' . $entityType . '/' . $layoutName . '.json';
    }

    private function applicationModuleLayoutPath(string $module, string $entityType, string $layoutName): string
    {
        return 'application/Espo/Modules/' . $module
            . '/Resources/layouts/' . $entityType . '/' . $layoutName . '.json';
    }

    /**
     * Vertical modules (e.g. SafehouseCrm) patch core entities before Crm layouts are merged.
     *
     * @return list<string>
     */
    private function preferredBaseLayoutModules(string $entityType): array
    {
        $preferred = ['NonprofitEspocrm'];
        $fromMetadata = $this->metadata->get("app.layouts.{$entityType}.detail.module");

        if (
            is_string($fromMetadata)
            && $fromMetadata !== ''
            && $fromMetadata !== self::MODULE
            && !in_array($fromMetadata, $preferred, true)
        ) {
            $preferred[] = $fromMetadata;
        }

        return $preferred;
    }
}
