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
    public function testVolunteerDoesNotGenerate(): void
    {
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(false, true, true, false));
    }

    public function testNewAssociatoGenerates(): void
    {
        $this->assertTrue(AdmissionPdfPlan::shouldGenerate(true, true, false, false));
    }

    public function testUnchangedAssociatoWithFileDoesNotRegenerate(): void
    {
        $this->assertFalse(AdmissionPdfPlan::shouldGenerate(true, false, false, true));
    }

    public function testConvertedAssociatoWithoutFileStillGenerates(): void
    {
        $this->assertTrue(AdmissionPdfPlan::shouldGenerate(true, false, false, false));
    }

    public function testReleaseOnlyWhenBothAssociato(): void
    {
        $this->assertTrue(AdmissionPdfPlan::shouldRelease(true, true, true, true));
        $this->assertFalse(AdmissionPdfPlan::shouldRelease(true, true, false, true));
        $this->assertFalse(AdmissionPdfPlan::shouldRelease(false, true, true, true));
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

    public function testDropWhenTypeLeavesAssociato(): void
    {
        $this->assertTrue(AdmissionPdfPlan::shouldDrop(false, false, true));
        $this->assertFalse(AdmissionPdfPlan::shouldDrop(true, false, true));
    }
}
