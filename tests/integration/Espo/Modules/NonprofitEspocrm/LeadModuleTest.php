<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Lead entity in navbar + ORM CRUD (subset of bin/smoke-lead-restore.php).
 */
class LeadModuleTest extends SafehouseBaseTestCase
{
    public function testLeadInNavAndOrmCrud(): void
    {
        $config = $this->getConfig();
        $tabStrings = array_values(array_filter($config->get('tabList', []) ?? [], 'is_string'));
        $qcStrings = array_values(array_filter($config->get('quickCreateList', []) ?? [], 'is_string'));

        $this->assertContains('Lead', $tabStrings);
        $this->assertContains('Lead', $qcStrings);

        $metadata = $this->getMetadata();
        $this->assertTrue((bool) $metadata->get(['scopes', 'Lead', 'entity']));

        $em = $this->getEntityManager();
        $marker = 'phpunit-lead-' . bin2hex(random_bytes(3));

        $lead = $em->createEntity('Lead', [
            'firstName' => 'PHPUnit',
            'lastName' => $marker,
            'status' => 'New',
        ]);

        $fresh = $em->getEntityById('Lead', $lead->getId());
        $this->assertNotNull($fresh);
        $this->assertSame('New', $fresh->get('status'));
        $this->assertStringContainsString($marker, (string) $fresh->get('name'));
    }
}
