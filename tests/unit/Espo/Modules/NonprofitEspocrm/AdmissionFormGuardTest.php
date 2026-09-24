<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionFormGuard;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class AdmissionFormGuardTest extends TestCase
{
    public function testRevertsClientSuppliedFile(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $name): bool => $name === 'admissionFormId' || $name === 'admissionFormName'
        );
        $entity->method('getFetched')->willReturnCallback(
            static fn (string $name): ?string => match ($name) {
                'admissionFormId' => 'owned-id',
                'admissionFormName' => 'owned.pdf',
                default => null,
            }
        );

        $set = [];
        $entity->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(
                function (string $name, mixed $value) use (&$set, $entity): Entity {
                    $set[$name] = $value;

                    return $entity;
                }
            );

        AdmissionFormGuard::revertClientChange($entity);

        $this->assertSame(
            [
                'admissionFormId' => 'owned-id',
                'admissionFormName' => 'owned.pdf',
            ],
            $set
        );
    }

    public function testLeavesUnchangedFileInPlace(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isAttributeChanged')->willReturn(false);
        $entity->expects($this->never())->method('set');

        AdmissionFormGuard::revertClientChange($entity);
    }
}
