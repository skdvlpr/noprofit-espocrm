<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * English schema invariants (converted from bin/smoke-schema-english.php).
 */
class SchemaEnglishTest extends SafehouseBaseTestCase
{
    public function testEnglishEnumKeysAndColumnLayout(): void
    {
        $em = $this->getEntityManager();
        $pdo = $em->getPDO();

        $columnExists = static function (string $table, string $column) use ($pdo): bool {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
            );
            $stmt->execute([':t' => $table, ':c' => $column]);

            return (bool) $stmt->fetchColumn();
        };

        $this->assertTrue($columnExists('account', 'sector'));
        $this->assertFalse($columnExists('account', 'settore'));
        $this->assertTrue($columnExists('opportunity', 'presentation_date'));
        $this->assertFalse($columnExists('opportunity', 'data_presentazione'));

        $marker = bin2hex(random_bytes(4));

        foreach (['ThirdSector', 'SocialWorkers', 'Public'] as $sectorKey) {
            $entity = $em->createEntity('Account', [
                'name' => "Sector {$sectorKey} {$marker}",
                'sector' => $sectorKey,
            ]);

            $fresh = $em->getRDBRepository('Account')->getById($entity->getId());
            $this->assertNotNull($fresh);
            $this->assertSame($sectorKey, $fresh->get('sector'));
        }

        $presentationDate = (new \DateTimeImmutable('today'))->format('Y-m-d');

        foreach (['Preparation', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'] as $stageKey) {
            $entity = $em->createEntity('Opportunity', [
                'name' => "Stage {$stageKey} {$marker}",
                'stage' => $stageKey,
                'presentationDate' => $presentationDate,
                'closeDate' => $presentationDate,
                'amount' => 100.0,
                'amountCurrency' => 'EUR',
            ]);

            $fresh = $em->getRDBRepository('Opportunity')->getById($entity->getId());
            $this->assertNotNull($fresh);
            $this->assertSame($stageKey, $fresh->get('stage'));
            $this->assertSame($presentationDate, $fresh->get('presentationDate'));
        }
    }
}
