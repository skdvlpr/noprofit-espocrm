<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdfPlan;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class AdmissionPdfPlanTest extends TestCase
{
    public function testNeverStoresOnSave(): void
    {
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(false, true, true, false));
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(true, true, false, false));
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(true, false, true, false));
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(true, false, false, false));
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(true, false, false, true));
    }

    public function testHasStoredFile(): void
    {
        $this->assertTrue(AdmissionPdfPlan::hasStoredFile('att-1'));
        $this->assertFalse(AdmissionPdfPlan::hasStoredFile(''));
        $this->assertFalse(AdmissionPdfPlan::hasStoredFile(null));
    }

    public function testClearOnConvertWhenEitherSideHasFile(): void
    {
        $this->assertTrue(AdmissionPdfPlan::shouldClearOnConvert(true, true, true, true, false));
        $this->assertTrue(AdmissionPdfPlan::shouldClearOnConvert(true, true, true, false, true));
        $this->assertFalse(AdmissionPdfPlan::shouldClearOnConvert(true, true, true, false, false));
        $this->assertFalse(AdmissionPdfPlan::shouldClearOnConvert(true, true, false, true, true));
        $this->assertFalse(AdmissionPdfPlan::shouldClearOnConvert(false, true, true, true, true));
    }

    public function testDownloadNameUsesNameAndBirth(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'firstName' => 'Semen',
                'lastName' => 'Koksharov',
                'birthDate' => '1990-09-16',
                default => null,
            }
        );

        $this->assertSame(
            'domanda ammissione semen koksharov 16091990.pdf',
            AdmissionPdf::downloadName($entity)
        );
    }

    public function testConvertedLeadIsNotDropped(): void
    {
        $this->assertFalse(AdmissionPdfPlan::shouldDrop(true, true, true));
        $this->assertFalse(AdmissionPdfPlan::shouldDrop(false, true, true));
    }

    public function testDropWhenTypeLeavesAssociato(): void
    {
        $this->assertTrue(AdmissionPdfPlan::shouldDrop(false, false, true));
        $this->assertFalse(AdmissionPdfPlan::shouldDrop(true, false, true));
        $this->assertFalse(AdmissionPdfPlan::shouldDrop(false, false, false));
    }
}
