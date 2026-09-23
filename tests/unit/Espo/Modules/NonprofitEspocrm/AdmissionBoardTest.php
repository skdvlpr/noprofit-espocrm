<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionBoard;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\LeadTaxCode;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 */
class AdmissionBoardTest extends TestCase
{
    public function testNonMemberCannotEdit(): void
    {
        $this->assertFalse(AdmissionBoard::mayEdit(true, false, ['Volunteer']));
    }

    public function testAdminAndMemberCanEdit(): void
    {
        $this->assertTrue(AdmissionBoard::mayEdit(true, true, []));
        $this->assertTrue(AdmissionBoard::mayEdit(true, false, ['Member']));
        $this->assertTrue(AdmissionBoard::mayEdit(false, false, []));
    }

    public function testEmptyCreateDoesNotCountAsBoardEdit(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isAttributeChanged')->willReturn(true);
        $entity->method('get')->willReturn(null);
        $entity->method('getFetched')->willReturn(null);

        $this->assertFalse(AdmissionBoard::isChanged($entity));
    }

    public function testAssertRejectsChangedBoardForNonMember(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $field): bool => $field === 'admissionOutcome'
        );
        $entity->method('get')->willReturn('Approved');
        $entity->method('getFetched')->willReturn(null);

        $this->expectException(Forbidden::class);
        AdmissionBoard::assertMaySave($entity, true, false, ['Volunteer']);
    }

    public function testAssertAllowsUnchangedBoard(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isAttributeChanged')->willReturn(false);

        AdmissionBoard::assertMaySave($entity, true, false, []);
        $this->addToAssertionCount(1);
    }

    public function testTaxCodeStripsSpacesAndKeepsOtherSymbols(): void
    {
        $this->assertSame('RSSMRA80A01H501U', LeadTaxCode::normalize('rss mra 80a01 h501u'));
        $this->assertSame('AAAAAA00A00A000A', LeadTaxCode::normalize('AAAAAA00A00A000A'));
        $this->assertSame('BAD-CODE', LeadTaxCode::normalize('bad-code'));
        $this->assertNull(LeadTaxCode::normalize('   '));
    }
}
