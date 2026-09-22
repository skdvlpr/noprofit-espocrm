<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\EmailRecipientResolver;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class EmailRecipientResolverTest extends TestCase
{
    public function testResolvePrimaryEmailRequiresRecordRead(): void
    {
        $contactId = 'victimContactId0001';
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnMap([
            ['emailAddressData', null],
            ['emailAddress', 'secret@example.test'],
        ]);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getEntityById')
            ->with('Contact', $contactId)
            ->willReturn($contact);

        $acl = $this->createMock(Acl::class);
        $acl->method('check')
            ->with('Contact', Table::ACTION_READ)
            ->willReturn(true);
        $acl->expects($this->once())
            ->method('checkEntityRead')
            ->with($contact)
            ->willReturn(false);
        $acl->expects($this->never())->method('checkField');

        $resolver = new EmailRecipientResolver($entityManager, $this->contactEmailMetadata(), $acl);

        $this->expectException(Forbidden::class);
        $resolver->resolvePrimaryEmail('Contact', $contactId);
    }

    public function testResolvePrimaryEmailRequiresEmailFieldRead(): void
    {
        $contactId = 'ownContactId0000001';
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnMap([
            ['emailAddressData', null],
            ['emailAddress', 'visible@example.test'],
        ]);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getEntityById')
            ->with('Contact', $contactId)
            ->willReturn($contact);

        $acl = $this->createMock(Acl::class);
        $acl->method('check')
            ->with('Contact', Table::ACTION_READ)
            ->willReturn(true);
        $acl->method('checkEntityRead')->with($contact)->willReturn(true);
        $acl->expects($this->once())
            ->method('checkField')
            ->with('Contact', 'emailAddress')
            ->willReturn(false);

        $resolver = new EmailRecipientResolver($entityManager, $this->contactEmailMetadata(), $acl);

        $this->expectException(Forbidden::class);
        $resolver->resolvePrimaryEmail('Contact', $contactId);
    }

    public function testResolvePrimaryEmailReturnsAddressWhenRecordAndFieldReadable(): void
    {
        $contactId = 'ownContactId0000002';
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnMap([
            ['emailAddressData', null],
            ['emailAddress', '  owner@example.test  '],
        ]);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getEntityById')
            ->with('Contact', $contactId)
            ->willReturn($contact);

        $acl = $this->createMock(Acl::class);
        $acl->method('check')
            ->with('Contact', Table::ACTION_READ)
            ->willReturn(true);
        $acl->method('checkEntityRead')->with($contact)->willReturn(true);
        $acl->method('checkField')->with('Contact', 'emailAddress')->willReturn(true);

        $resolver = new EmailRecipientResolver($entityManager, $this->contactEmailMetadata(), $acl);

        $this->assertSame(
            'owner@example.test',
            $resolver->resolvePrimaryEmail('Contact', $contactId)
        );
    }

    private function contactEmailMetadata(): Metadata
    {
        $metadata = $this->createMock(Metadata::class);
        $metadata->method('get')->willReturnCallback(
            static function (array $path, mixed $default = null): mixed {
                if ($path === ['entityDefs', 'Contact', 'fields']) {
                    return ['emailAddress' => ['type' => 'email']];
                }

                return $default;
            }
        );

        return $metadata;
    }
}
