<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Utils\Language;

/**
 * Resolves CRM entity labels for calendar pickers (UI only).
 * Entity type keys stay English; labels come from i18n (SafehouseCrm overrides first).
 */
class EntityScopeNameTranslator
{
    /** @var list<string|null> */
    private const SCOPE_PRIORITY = [
        'NonprofitEspocrm',
        'GoogleIntegration',
        'Global',
        null,
    ];

    public function __construct(
        private Language $language
    ) {}

    public function translate(string $entityType): ?string
    {
        foreach (['scopeNames', 'scopeNamesPlural'] as $category) {
            foreach (self::SCOPE_PRIORITY as $scope) {
                $translated = $scope === null
                    ? $this->language->translate($entityType, $category)
                    : $this->language->translate($entityType, $category, $scope);

                // Do not require $translated !== $entityType: many locales use the English
                // scope key as the label (e.g. Account → "Account"). That is a valid label.
                if (is_string($translated) && $translated !== '') {
                    return $translated;
                }
            }
        }

        return null;
    }
}
