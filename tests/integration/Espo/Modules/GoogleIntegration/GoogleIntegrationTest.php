<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\Modules\GoogleIntegration\Tools\OAuth\RedirectUri;
use tests\integration\Espo\Support\SafehouseBaseTestCase;
class GoogleIntegrationTest extends SafehouseBaseTestCase
{
    public function testIntegrationRowAndMetadata(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $metadata = $this->getMetadata();
        $integrationMeta = $metadata->get(['integrations', Installer::INTEGRATION_ID]);

        $this->assertIsArray($integrationMeta);
        $this->assertSame('OAuth2', $integrationMeta['authMethod'] ?? null);
        $this->assertSame(
            'google-integration:views/admin/integrations/edit',
            $integrationMeta['view'] ?? null
        );
        $this->assertSame(
            'google-integration:views/external-account/oauth2',
            $integrationMeta['userView'] ?? null
        );
        $this->assertSame(
            'Espo\\Modules\\GoogleIntegration\\Core\\ExternalAccount\\Clients\\Google',
            $integrationMeta['clientClassName'] ?? null
        );
        $this->assertEmpty($integrationMeta['params']['redirectUriPath'] ?? null);

        $scope = (string) (($integrationMeta['params'] ?? [])['scope'] ?? '');
        $this->assertStringContainsString('openid', $scope);
        $this->assertStringContainsString('email', $scope);
        $this->assertStringContainsString('profile', $scope);
        $this->assertStringContainsString('calendar', $scope);
        $this->assertStringContainsString('drive.file', $scope);

        $fields = $integrationMeta['fields'] ?? null;
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('clientId', $fields);
        $this->assertArrayHasKey('clientSecret', $fields);
        $this->assertArrayHasKey('googleCalendarAutoCreateEnabled', $fields);
        $this->assertSame('CRM', $fields['googleCalendarNamePrefix']['default'] ?? null);

        $expectedRedirect = (string) ($this->getConfig()->get('siteUrl') ?? '') . '?entryPoint=oauthCallback';
        $this->assertSame($expectedRedirect, RedirectUri::build($this->getConfig()));

        $em = $this->getEntityManager();
        $integration = $em->getRDBRepository('Integration')
            ->where(['id' => Installer::INTEGRATION_ID])
            ->findOne();

        $this->assertNotNull($integration);

        $legacy = $em->getRDBRepository('Integration')
            ->where(['id' => 'GoogleSafehouse'])
            ->findOne();

        $this->assertNull($legacy);
    }

    public function testScheduledJobsRegistered(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $metadata = $this->getMetadata();

        $syncCalendar = $metadata->get(['app', 'scheduledJobs', 'GoogleIntegrationSyncCalendar']) ?? [];
        $this->assertIsArray($syncCalendar);
        $this->assertStringContainsString('SyncCalendar', (string) ($syncCalendar['jobClassName'] ?? ''));

        $syncOverlay = $metadata->get(['app', 'scheduledJobs', 'GoogleIntegrationSyncOverlayCalendars']) ?? [];
        $this->assertIsArray($syncOverlay);
        $this->assertStringContainsString('SyncOverlayCalendars', (string) ($syncOverlay['jobClassName'] ?? ''));

        $this->assertTrue(class_exists(\Espo\Modules\GoogleIntegration\Jobs\SyncCalendar::class));
        $this->assertTrue(class_exists(\Espo\Modules\GoogleIntegration\Jobs\SyncOverlayCalendars::class));
    }

    public function testCalendarEntityMetadata(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $metadata = $this->getMetadata();

        $calendarDateSourceFields = $metadata->get(['entityDefs', 'CalendarDateSource', 'fields']) ?? [];
        $this->assertIsArray($calendarDateSourceFields);
        $this->assertArrayHasKey('targetEntityType', $calendarDateSourceFields);
        $this->assertArrayHasKey('dateField', $calendarDateSourceFields);
        $this->assertArrayHasKey('sourceDateType', $calendarDateSourceFields);
        $this->assertArrayHasKey('calendarRoutingMode', $calendarDateSourceFields);
        $this->assertSame('varchar', $calendarDateSourceFields['targetEntityType']['type'] ?? null);

        $routingOptions = $calendarDateSourceFields['calendarRoutingMode']['options'] ?? [];
        $this->assertContains('user_pick', $routingOptions);
        $this->assertContains('auto_dedicated', $routingOptions);

        $calendarDateSourceScope = $metadata->get(['scopes', 'CalendarDateSource']) ?? [];
        $this->assertTrue($calendarDateSourceScope['entity'] ?? false);
        $this->assertSame('Base', $calendarDateSourceScope['type'] ?? null);
        $this->assertFalse($calendarDateSourceScope['tab'] ?? true);
        $this->assertSame(
            'fas fa-calendar-day',
            $metadata->get(['clientDefs', 'CalendarDateSource', 'iconClass'])
        );
        $this->assertSame('all', $metadata->get(['aclDefs', 'CalendarDateSource', 'edit']));

        $calendarTemplateFields = $metadata->get(['entityDefs', 'CalendarTemplate', 'fields']) ?? [];
        $this->assertIsArray($calendarTemplateFields);
        $this->assertArrayHasKey('targetEntityType', $calendarTemplateFields);
        $this->assertArrayHasKey('summaryTemplate', $calendarTemplateFields);
        $this->assertArrayHasKey('descriptionTemplate', $calendarTemplateFields);
        $this->assertArrayHasKey('reminders', $calendarTemplateFields);
        $this->assertSame('varchar', $calendarTemplateFields['targetEntityType']['type'] ?? null);

        $calendarTemplateScope = $metadata->get(['scopes', 'CalendarTemplate']) ?? [];
        $this->assertTrue($calendarTemplateScope['entity'] ?? false);
        $this->assertSame('BasePlus', $calendarTemplateScope['type'] ?? null);
        $this->assertFalse($calendarTemplateScope['tab'] ?? true);
        $this->assertSame(
            'fas fa-calendar-check',
            $metadata->get(['clientDefs', 'CalendarTemplate', 'iconClass'])
        );
        $this->assertSame('all', $metadata->get(['aclDefs', 'CalendarTemplate', 'read']));

        $linkFields = $metadata->get(['entityDefs', 'GoogleCalendarEventLink', 'fields']) ?? [];
        $this->assertArrayHasKey('sourceEntityType', $linkFields);
        $this->assertArrayHasKey('sourceDateType', $linkFields);
        $this->assertArrayHasKey('googleEventId', $linkFields);
        $this->assertArrayHasKey('user', $linkFields);

        $this->assertTrue($metadata->get(['scopes', 'GoogleCalendarOverlayEvent', 'entity']) === true);
        $this->assertTrue($metadata->get(['clientDefs', 'GoogleCalendarOverlayEvent', 'createDisabled']) === true);
        $this->assertTrue($metadata->get(['clientDefs', 'GoogleCalendarOverlayEvent', 'editDisabled']) === true);
        $this->assertTrue($metadata->get(['clientDefs', 'GoogleCalendarOverlayEvent', 'deleteDisabled']) === true);

        $this->assertSame(
            30,
            \Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner::RETENTION_DAYS
        );
    }
}
