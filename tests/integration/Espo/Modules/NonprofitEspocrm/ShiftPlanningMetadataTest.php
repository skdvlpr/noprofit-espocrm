<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Shift planning client metadata (subset of bin/smoke-shift-planning.php).
 */
class ShiftPlanningMetadataTest extends SafehouseBaseTestCase
{
    public function testActivityOfferScopeAndLifecycleButtons(): void
    {
        $metadata = $this->getMetadata();

        $this->assertTrue((bool) $metadata->get(['scopes', 'ActivityOffer', 'entity']));
        $this->assertSame('NonprofitEspocrm', $metadata->get(['scopes', 'ActivityOffer', 'module']));

        $buttons = $metadata->get(['clientDefs', 'ActivityOffer', 'detailButtonList']) ?? [];
        $buttonNames = array_map(
            static fn ($item) => is_object($item) ? ($item->name ?? '') : ($item['name'] ?? ''),
            $buttons
        );

        foreach (['requestAvailability', 'autoAssign', 'confirmPlan'] as $action) {
            $this->assertContains(
                $action,
                $buttonNames,
                'Expected lifecycle action in ActivityOffer detailButtonList: ' . $action
            );
        }

        $root = dirname(__DIR__, 5);
        $this->assertFileExists(
            $root . '/client/custom/modules/nonprofit-espocrm/src/handlers/activity-offer/shift-actions.js'
        );
    }
}
