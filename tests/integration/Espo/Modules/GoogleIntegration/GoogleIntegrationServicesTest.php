<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\GoogleIntegration;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Util;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateTimeResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDisplayDateResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CrmDateSourceEventFetcher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarExportGuard;
use Espo\Modules\GoogleIntegration\Tools\Calendar\SyncMode;
use Espo\Modules\GoogleIntegration\Tools\ExternalAccount\AccountProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * GoogleIntegration services that need the Espo container but not live Google OAuth.
 */
class GoogleIntegrationServicesTest extends SafehouseBaseTestCase
{
    public function testAccountProvisionerMigratesLegacyRowAndIsIdempotent(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $em = $this->getEntityManager();
        $injectableFactory = $this->getContainer()->getByClass(InjectableFactory::class);
        $accountProvisioner = $injectableFactory->create(AccountProvisioner::class);

        $provisionUserId = 'gi_prov_' . substr(Util::generateId(), 0, 8);
        $legacyProvisionId = 'GoogleIntegration__' . $provisionUserId;
        $canonicalProvisionId = Installer::INTEGRATION_ID . '__' . $provisionUserId;
        $pdo = $em->getPDO();

        $pdo->exec(
            'DELETE FROM external_account WHERE id IN ('
            . $pdo->quote($legacyProvisionId) . ',' . $pdo->quote($canonicalProvisionId) . ')'
        );

        $legacySeed = $em->createEntity('ExternalAccount', [
            'id' => $legacyProvisionId,
            'enabled' => true,
        ]);
        $legacySeed->set('data', (object) ['calendarSyncMode' => 'crmToGoogle', 'smokeToken' => 'legacy']);
        $em->saveEntity($legacySeed);

        $provisioned = $accountProvisioner->ensureForUser($provisionUserId);
        $this->assertSame($canonicalProvisionId, $provisioned->getId());
        $this->assertTrue($provisioned->get('enabled'));

        $migratedData = $provisioned->get('data');
        $migratedToken = is_object($migratedData)
            ? ($migratedData->smokeToken ?? null)
            : (is_array($migratedData) ? ($migratedData['smokeToken'] ?? null) : null);
        $this->assertSame('legacy', $migratedToken);

        $again = $accountProvisioner->ensureForUser($provisionUserId);
        $this->assertSame($canonicalProvisionId, $again->getId());

        $pdo->exec(
            'DELETE FROM external_account WHERE id IN ('
            . $pdo->quote($legacyProvisionId) . ',' . $pdo->quote($canonicalProvisionId) . ')'
        );
    }

    public function testIntegrationStateReflectsDisabledRow(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $em = $this->getEntityManager();
        $injectableFactory = $this->getContainer()->getByClass(InjectableFactory::class);
        $integrationState = $injectableFactory->create(IntegrationState::class);

        $integration = $em->getEntityById('Integration', Installer::INTEGRATION_ID);
        $this->assertNotNull($integration);

        $wasEnabled = (bool) $integration->get('enabled');
        $integration->set('enabled', false);
        $em->saveEntity($integration);
        $this->getConfig()->update();

        try {
            $this->assertFalse($integrationState->isGoogleCalendarDriveEnabled());
        } finally {
            $integration->set('enabled', $wasEnabled);
            $em->saveEntity($integration);
            $this->getConfig()->update();
        }
    }

    public function testExportGuardRejectsSaveWhenIntegrationDisabled(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $em = $this->getEntityManager();
        $injectableFactory = $this->getContainer()->getByClass(InjectableFactory::class);
        $guard = $injectableFactory->create(GoogleCalendarExportGuard::class);

        $integration = $em->getEntityById('Integration', Installer::INTEGRATION_ID);
        $this->assertNotNull($integration);

        $wasEnabled = (bool) $integration->get('enabled');
        $integration->set('enabled', false);
        $em->saveEntity($integration);
        $this->getConfig()->update();

        $meeting = $em->getNewEntity('Meeting');
        $meeting->set([
            'id' => Util::generateId(),
            'name' => 'GI export guard ' . substr(Util::generateId(), 0, 8),
            'saveToGoogleCalendar' => true,
        ]);

        $this->assertTrue(
            $meeting->get('saveToGoogleCalendar'),
            'Meeting.saveToGoogleCalendar must be provisioned in metadata (CalendarDateSource bootstrap).'
        );

        try {
            $guard->assertExportAllowed($meeting);
            $this->fail('Expected BadRequest when export requested while integration disabled.');
        } catch (BadRequest $e) {
            $this->assertStringContainsString('Google Calendar integration is disabled', $e->getMessage());
        } finally {
            $integration->set('enabled', $wasEnabled);
            $em->saveEntity($integration);
            $this->getConfig()->update();
        }
    }

    public function testExternalAccountCalendarSyncModeCoercedOnSave(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $em = $this->getEntityManager();
        $admin = $em->getRDBRepository('User')
            ->where(['userName' => 'admin', 'deleted' => false])
            ->findOne();

        if ($admin === null) {
            $this->markTestSkipped('Admin user not found.');
        }

        $externalAccountId = Installer::INTEGRATION_ID . '__' . $admin->getId();
        $externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);

        if ($externalAccount === null) {
            $externalAccount = $em->createEntity('ExternalAccount', [
                'id' => $externalAccountId,
                'enabled' => false,
            ]);
        }

        $externalAccount->set('calendarSyncMode', SyncMode::CRM_TO_GOOGLE);
        $em->saveEntity($externalAccount);

        $externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);
        $this->assertNotNull($externalAccount);
        $this->assertSame(SyncMode::NONE, $externalAccount->get('calendarSyncMode'));
    }

    public function testCalendarProvisionerDedicatedCalendarNaming(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $injectableFactory = $this->getContainer()->getByClass(InjectableFactory::class);
        $calendarProvisioner = $injectableFactory->create(CalendarProvisioner::class);

        $presentationName = $calendarProvisioner->resolveDedicatedCalendarName([
            'targetEntityType' => 'Opportunity',
            'sourceDateType' => 'presentationDate',
            'label' => 'Presentation',
            'dedicatedCalendarName' => '',
        ]);
        $closeName = $calendarProvisioner->resolveDedicatedCalendarName([
            'targetEntityType' => 'Opportunity',
            'sourceDateType' => 'closeDate',
            'label' => 'Close',
            'dedicatedCalendarName' => '',
        ]);
        $customName = $calendarProvisioner->resolveDedicatedCalendarName([
            'targetEntityType' => 'Opportunity',
            'sourceDateType' => 'presentationDate',
            'label' => 'Presentation',
            'dedicatedCalendarName' => 'CRM - Custom Funds',
        ]);

        $entityLabel = $calendarProvisioner->resolveEntityLabel('Opportunity');

        $this->assertTrue(
            str_starts_with($presentationName, 'CRM-') || str_starts_with($presentationName, 'CRM - ')
        );
        $this->assertStringNotContainsString('presentation', strtolower($presentationName));
        if ($entityLabel !== '') {
            $this->assertStringContainsString($entityLabel, $presentationName);
        }
        $this->assertSame($presentationName, $closeName);
        $this->assertSame('CRM - Custom Funds', $customName);
        $this->assertSame('CRM-Meeting', $calendarProvisioner->buildCalendarName('Meeting'));
        $this->assertSame('Meeting', $calendarProvisioner->extractLabelFromCalendarName('CRM-Meeting'));
        $this->assertTrue($calendarProvisioner->isCrmCalendarName('CRM-Meeting'));
        $this->assertFalse($calendarProvisioner->isCrmCalendarName('Personal'));
    }

    public function testDateSourceProviderCanonicalSourceDateType(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $injectableFactory = $this->getContainer()->getByClass(InjectableFactory::class);
        $dateSourceProvider = $injectableFactory->create(DateSourceProvider::class);

        $this->assertSame('main', $dateSourceProvider->canonicalSourceDateType('Meeting', 'main'));

        $memberSources = $dateSourceProvider->getActiveSourcesForEntityType('Member');

        if ($memberSources !== []) {
            $memberFirstKey = (string) ($memberSources[0]['sourceDateType'] ?? 'main');
            $this->assertSame(
                $memberFirstKey,
                $dateSourceProvider->canonicalSourceDateType('Member', 'main')
            );
            $this->assertSame(
                $memberFirstKey,
                $dateSourceProvider->canonicalSourceDateType('Member', $memberFirstKey)
            );
        } else {
            $this->markTestSkipped('No Member CalendarDateSource rows seeded.');
        }
    }

    public function testCalendarDateAndDisplayResolvers(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $injectableFactory = $this->getContainer()->getByClass(InjectableFactory::class);
        $dateTimeResolver = $injectableFactory->create(CalendarDateTimeResolver::class);
        $displayDateResolver = $injectableFactory->create(CalendarDisplayDateResolver::class);

        $appTz = (string) ($this->getConfig()->get('timeZone') ?? 'UTC');
        $this->assertSame($appTz, $dateTimeResolver->getExportTimeZone());

        $meeting = $this->getEntityManager()->getNewEntity('Meeting');
        $meeting->set([
            'dateStart' => '2026-06-15 08:00:00',
            'dateEnd' => '2026-06-15 09:00:00',
        ]);

        $resolvedDate = $displayDateResolver->resolveDateOnly($meeting, 'dateStart');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $resolvedDate);

        $range = $dateTimeResolver->buildGoogleTimedRange(
            '2026-06-15 08:00:00',
            '2026-06-15 08:45:00'
        );
        $this->assertSame($appTz, $range['start']['timeZone']);
        $this->assertSame($appTz, $range['end']['timeZone']);
    }

    public function testCrmDateSourceFetcherHonorsAclAndDateRange(): void
    {
        if (!class_exists(CrmDateSourceEventFetcher::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $em = $this->getEntityManager();
        $source = $em->getRDBRepository('CalendarDateSource')
            ->where([
                'targetEntityType' => 'Opportunity',
                'dateField' => 'closeDate',
                'isActive' => true,
                'calendarViewEnabled' => true,
                'deleted' => false,
            ])
            ->findOne();

        if ($source === null) {
            $this->markTestSkipped('Opportunity closeDate calendar view source not seeded.');
        }

        $marker = substr(Util::generateId(), 0, 8);
        $inRange = date('Y-m-d');
        $outOfRange = date('Y-m-d', strtotime('-2 years'));

        $viewer = $this->createUser([
            'userName' => 'phpunit_cds_' . $marker,
            'firstName' => 'PHPUnit',
            'lastName' => 'CdsViewer',
            'type' => 'regular',
            'isActive' => true,
        ], [
            'name' => 'phpunit_cds_role_' . $marker,
            'data' => [
                'Calendar' => [
                    'read' => 'yes',
                    'edit' => 'no',
                ],
                'Opportunity' => [
                    'create' => 'no',
                    'read' => 'own',
                    'edit' => 'own',
                    'delete' => 'no',
                    'stream' => 'no',
                ],
            ],
        ]);

        $foreign = $this->createUser([
            'userName' => 'phpunit_cds_other_' . $marker,
            'firstName' => 'PHPUnit',
            'lastName' => 'CdsOther',
            'type' => 'regular',
            'isActive' => true,
        ]);

        $ownVisible = $em->getNewEntity('Opportunity');
        $ownVisible->set([
            'name' => 'PHPUnit CDS own in-range ' . $marker,
            'closeDate' => $inRange,
            'amount' => 100,
            'amountCurrency' => 'EUR',
            'assignedUserId' => $viewer->getId(),
        ]);
        $em->saveEntity($ownVisible);

        $ownHidden = $em->getNewEntity('Opportunity');
        $ownHidden->set([
            'name' => 'PHPUnit CDS own out-of-range ' . $marker,
            'closeDate' => $outOfRange,
            'amount' => 100,
            'amountCurrency' => 'EUR',
            'assignedUserId' => $viewer->getId(),
        ]);
        $em->saveEntity($ownHidden);

        $foreignVisible = $em->getNewEntity('Opportunity');
        $foreignVisible->set([
            'name' => 'PHPUnit CDS foreign in-range ' . $marker,
            'closeDate' => $inRange,
            'amount' => 100,
            'amountCurrency' => 'EUR',
            'assignedUserId' => $foreign->getId(),
        ]);
        $em->saveEntity($foreignVisible);

        $this->authenticate($viewer->get('userName'));

        $fetcher = $this->getContainer()
            ->getByClass(InjectableFactory::class)
            ->create(CrmDateSourceEventFetcher::class);

        $events = $fetcher->fetch($inRange . ' 00:00:00', $inRange . ' 23:59:59', ['Opportunity']);
        $ids = array_map(static fn ($event): string => $event->getId(), $events);

        $this->assertContains($ownVisible->getId(), $ids);
        $this->assertNotContains($ownHidden->getId(), $ids);
        $this->assertNotContains($foreignVisible->getId(), $ids);
    }
}
