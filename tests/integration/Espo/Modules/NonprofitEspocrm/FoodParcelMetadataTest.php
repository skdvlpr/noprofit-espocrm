<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * FoodParcelRegistration metadata (subset of bin/smoke-food-parcel.php).
 */
class FoodParcelMetadataTest extends SafehouseBaseTestCase
{
    public function testFoodParcelRegistrationScopeAndFields(): void
    {
        $metadata = $this->getMetadata();

        $this->assertTrue((bool) $metadata->get(['scopes', 'FoodParcelRegistration', 'entity']));
        $this->assertTrue((bool) $metadata->get(['scopes', 'FoodParcelRegistration', 'stream']));
        $this->assertFalse(
            (bool) ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'optimisticConcurrencyControl']) ?? true)
        );

        $entryDates = $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'entryDates']) ?? [];
        $this->assertIsArray($entryDates);
        $this->assertArrayNotHasKey('optimisticConcurrencyControlIgnore', $entryDates);

        $this->assertTrue(
            class_exists(\Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelPdfService::class)
        );
    }
}
