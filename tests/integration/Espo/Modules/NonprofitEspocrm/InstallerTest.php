<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Installer;
use integration\Core\NoTransaction;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Post-install navbar/tab provisioning (converted from bin/smoke-installer.php).
 */
#[NoTransaction]
class InstallerTest extends SafehouseBaseTestCase
{
    public function testPostInstallProvisioningAndIdempotence(): void
    {
        $config = $this->getConfig();
        $tabList = $config->get('tabList', []) ?? [];
        $tabStrings = array_values(array_filter($tabList, 'is_string'));

        $this->assertContains('Lead', $tabStrings);
        $this->assertContains('Case', $tabStrings);
        $this->assertContains('Intervention', $tabStrings);
        $this->assertContains('FoodParcelRegistration', $tabStrings);
        $this->assertNotContains('VolunteerEmployee', $tabStrings);
        $this->assertNotContains('Member', $tabStrings);
        $this->assertNotContains('MealCount', $tabStrings);

        $metadata = $this->getMetadata();
        $this->assertNotSame(true, $metadata->get(['scopes', 'VolunteerEmployee', 'entity']));
        $this->assertNotSame(true, $metadata->get(['scopes', 'Member', 'entity']));

        $reportingGroup = null;

        foreach ($tabList as $item) {
            if (
                is_object($item)
                && ($item->type ?? null) === 'group'
                && ($item->text ?? null) === '$Rendicontazione'
            ) {
                $reportingGroup = $item;
                break;
            }
        }

        $this->assertNotNull($reportingGroup);
        $groupItems = $reportingGroup->itemList ?? [];
        $this->assertContains('MealCount', $groupItems);
        $this->assertContains('PrimaNota', $groupItems);
        $this->assertNotContains('Opportunity', $groupItems);

        $countBefore = count($tabList);
        (new Installer())->runPostInstall($this->getContainer());
        $config->update();
        $tabListAfter = $config->get('tabList', []) ?? [];

        $this->assertCount($countBefore, $tabListAfter);
    }
}
