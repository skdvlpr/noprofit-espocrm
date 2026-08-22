<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\TaxCode\ItalianFiscalCode;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;
use stdClass;

class ItalianFiscalCodeTest extends TestCase
{
    private ItalianFiscalCode $validator;

    protected function setUp(): void
    {
        $this->validator = new ItalianFiscalCode();
    }

    public function testNullOrEmptyPasses(): void
    {
        $data = new Data(new stdClass());

        $this->assertNull($this->validator->validate($this->entityWith('taxCode', null), 'taxCode', $data));
        $this->assertNull($this->validator->validate($this->entityWith('taxCode', ''), 'taxCode', $data));
    }

    public function testValidCodiceFiscalePasses(): void
    {
        $data = new Data(new stdClass());
        $entity = $this->entityWith('taxCode', 'rssmra85t10a562s');

        $this->assertNull($this->validator->validate($entity, 'taxCode', $data));
    }

    public function testValidPartitaIvaPasses(): void
    {
        $data = new Data(new stdClass());
        $entity = $this->entityWith('taxCode', '12345678901');

        $this->assertNull($this->validator->validate($entity, 'taxCode', $data));
    }

    public function testInvalidFormatFails(): void
    {
        $data = new Data(new stdClass());

        $this->assertInstanceOf(
            Failure::class,
            $this->validator->validate($this->entityWith('taxCode', 'NOT-A-VALID-CODE'), 'taxCode', $data)
        );
    }

    public function testNonStringValueFails(): void
    {
        $data = new Data(new stdClass());

        $this->assertInstanceOf(
            Failure::class,
            $this->validator->validate($this->entityWith('taxCode', 12345678901), 'taxCode', $data)
        );
    }

    private function entityWith(string $field, mixed $value): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->with($field)->willReturn($value);

        return $entity;
    }
}
