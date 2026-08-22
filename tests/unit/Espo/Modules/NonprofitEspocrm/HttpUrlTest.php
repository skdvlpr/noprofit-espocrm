<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\AccountWebsite\Name\HttpUrl;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;
use stdClass;

class HttpUrlTest extends TestCase
{
    private HttpUrl $validator;

    protected function setUp(): void
    {
        $this->validator = new HttpUrl();
    }

    public function testNullOrEmptyPasses(): void
    {
        $data = new Data(new stdClass());

        $this->assertNull($this->validator->validate($this->entityWith('name', null), 'name', $data));
        $this->assertNull($this->validator->validate($this->entityWith('name', ''), 'name', $data));
    }

    public function testHttpsUrlPasses(): void
    {
        $data = new Data(new stdClass());

        $this->assertNull(
            $this->validator->validate($this->entityWith('name', 'https://www.example.org'), 'name', $data)
        );
    }

    public function testBareHostGetsHttpsAndPasses(): void
    {
        $data = new Data(new stdClass());

        $this->assertNull(
            $this->validator->validate($this->entityWith('name', 'www.example.org'), 'name', $data)
        );
    }

    public function testDisallowedSchemeFails(): void
    {
        $data = new Data(new stdClass());

        $this->assertInstanceOf(
            Failure::class,
            $this->validator->validate($this->entityWith('name', 'ftp://files.example.org'), 'name', $data)
        );
    }

    public function testHostWithoutDotFails(): void
    {
        $data = new Data(new stdClass());

        $this->assertInstanceOf(
            Failure::class,
            $this->validator->validate($this->entityWith('name', 'https://localhost'), 'name', $data)
        );
    }

    public function testNonStringValueFails(): void
    {
        $data = new Data(new stdClass());

        $this->assertInstanceOf(
            Failure::class,
            $this->validator->validate($this->entityWith('name', ['https://example.org']), 'name', $data)
        );
    }

    private function entityWith(string $field, mixed $value): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->with($field)->willReturn($value);

        return $entity;
    }
}
