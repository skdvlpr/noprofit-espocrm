<?php
/**
 * REST smoke for the standalone **`GoogleCalendarDrive`** Espo extension (universal
 * Google OAuth2: Calendar + `drive.file` Drive scope via core ExternalAccount).
 *
 * 1) Runs {@see \Espo\Modules\GoogleIntegration\Tools\Installer} (idempotent: DB row,
 *    removes legacy `GoogleSafehouse` integration id, rebuild).
 * 2) Follows `explore-espo-endpoints` Workflow A (`App/user`) + Workflow C (Metadata slice).
 * 3) ORM + expected **403** on `GET Integration/GoogleCalendarDrive` for `type=api` users
 *    (human admin UI uses `type=admin`).
 *
 * Usage:
 *   ddev exec php bin/smoke-google-integration.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SMOKE_USER_ADMIN = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);

echo "Provisioning: Espo\\Modules\\GoogleIntegration\\Tools\\Installer\n";
(new GoogleIntegrationInstaller())->runPostInstall($container);
$config->update();

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty.\n");
    exit(1);
}

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$layoutHasField = static function (mixed $layout, string $fieldName) use (&$layoutHasField): bool {
    if (is_array($layout)) {
        foreach ($layout as $item) {
            if ($layoutHasField($item, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    if (is_object($layout)) {
        if (($layout->name ?? null) === $fieldName) {
            return true;
        }

        foreach (get_object_vars($layout) as $value) {
            if ($layoutHasField($value, $fieldName)) {
                return true;
            }
        }
    }

    return false;
};

$role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();
if ($role === null) {
    fwrite(STDERR, "FAIL: Admin role not found.\n");
    exit(1);
}
$roleId = $role->getId();

/** @var ?User $user */
$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER_ADMIN, 'deleted' => false])
    ->findOne();
if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName'   => SMOKE_USER_ADMIN,
        'type'       => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds'   => [$roleId],
        'firstName'  => 'Smoke',
        'lastName'   => 'GoogleMeta',
    ]);
    $em->saveEntity($user);
    $user = $em->getRDBRepository('User')->getById($user->getId());
}
$apiKey = $user->get('apiKey');
if (!is_string($apiKey) || $apiKey === '') {
    $apiKey = Util::generateApiKey();
    $user->set('apiKey', $apiKey);
    $em->saveEntity($user);
}

$client = new Client([
    'base_uri'    => $siteUrl,
    'verify'      => false,
    'timeout'     => 30,
    'http_errors' => false,
    'headers'     => [
        'X-Api-Key' => $apiKey,
        'Accept'    => 'application/json',
    ],
]);

echo "Base URL: $siteUrl\n";
echo "Workflow A: App/user (X-Api-Key)\n";

try {
    $r = $client->get('/api/v1/App/user');
} catch (RequestException $e) {
    fwrite(STDERR, 'HTTP error: ' . $e->getMessage() . "\n");
    exit(1);
}
$ok('GET /api/v1/App/user → 200', $r->getStatusCode() === 200, 'code=' . $r->getStatusCode());

echo "\nWorkflow C: Metadata integrations.GoogleCalendarDrive\n";

$rMeta = $client->get('/api/v1/Metadata', ['query' => ['key' => 'integrations.GoogleCalendarDrive']]);
$ok('GET Metadata?key=integrations.GoogleCalendarDrive → 200', $rMeta->getStatusCode() === 200,
    'code=' . $rMeta->getStatusCode());
$meta = json_decode((string) $rMeta->getBody(), true);
$ok('authMethod OAuth2', ($meta['authMethod'] ?? '') === 'OAuth2');
$ok(
    'admin view uses module client path (google-integration:…)',
    ($meta['view'] ?? '') === 'google-integration:views/admin/integrations/edit'
);
$ok(
    'userView uses module client path (google-integration:…)',
    ($meta['userView'] ?? '') === 'google-integration:views/external-account/oauth2'
);
$ok(
    'clientClassName is module Google client',
    ($meta['clientClassName'] ?? '') === 'Espo\\Modules\\GoogleIntegration\\Core\\ExternalAccount\\Clients\\Google'
);
$ok('no custom redirectUriPath (canonical ?entryPoint=oauthCallback)', empty($meta['params']['redirectUriPath'] ?? null));
$expectedRedirect = (string) ($config->get('siteUrl') ?? '') . '?entryPoint=oauthCallback';
$ok('canonical redirect URI matches Espo core ClientManager', \Espo\Modules\GoogleIntegration\Tools\OAuth\RedirectUri::build($config) === $expectedRedirect, $expectedRedirect);
$scope = (string) (($meta['params'] ?? [])['scope'] ?? '');
$ok(
    'scope lists identity, calendar and drive.file',
    str_contains($scope, 'openid')
    && str_contains($scope, 'email')
    && str_contains($scope, 'profile')
    && str_contains($scope, 'calendar')
    && str_contains($scope, 'drive.file')
);
$fields = $meta['fields'] ?? null;
$ok('fields include clientId and clientSecret', is_array($fields) && isset($fields['clientId'], $fields['clientSecret']));

echo "\nGoogle Calendar entity metadata\n";

foreach (['Meeting', 'Call', 'Task', 'Opportunity'] as $entityType) {
    $rEntityDefs = $client->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.' . $entityType]]);
    $ok("GET Metadata?key=entityDefs.$entityType → 200", $rEntityDefs->getStatusCode() === 200,
        'code=' . $rEntityDefs->getStatusCode());

    $entityDefs = json_decode((string) $rEntityDefs->getBody(), true);
    $entityFields = $entityDefs['fields'] ?? [];

    $ok(
        "$entityType has saveToGoogleCalendar",
        is_array($entityFields) && isset($entityFields['saveToGoogleCalendar'])
    );
    $ok(
        "$entityType has googleCalendarReminderMode",
        is_array($entityFields) && isset($entityFields['googleCalendarReminderMode'])
    );
    $ok(
        "$entityType has googleCalendarReminders",
        is_array($entityFields) && isset($entityFields['googleCalendarReminders'])
    );
    $ok(
        "$entityType has googleCalendarDescriptionTemplateOverride",
        is_array($entityFields) && isset($entityFields['googleCalendarDescriptionTemplateOverride'])
    );
    $ok(
        "$entityType has googleCalendarTemplate link",
        is_array($entityFields) && isset($entityFields['googleCalendarTemplate'])
    );
    $ok(
        "$entityType template override uses field view",
        is_array($entityFields)
        && ($entityFields['googleCalendarDescriptionTemplateOverride']['view'] ?? null)
            === 'google-integration:views/fields/google-calendar-description-template'
    );
    $ok(
        "$entityType Google color uses color field view",
        is_array($entityFields)
        && ($entityFields['googleCalendarColorId']['view'] ?? null)
            === 'google-integration:views/fields/google-calendar-color'
    );
    $ok(
        "$entityType Opportunity date selector scope is correct",
        $entityType === 'Opportunity'
            ? isset($entityFields['googleCalendarOpportunityDateList'])
            : !isset($entityFields['googleCalendarOpportunityDateList'])
    );
    $ok(
        "$entityType Opportunity per-date settings scope is correct",
        $entityType === 'Opportunity'
            ? isset($entityFields['googleCalendarOpportunityEventSettings'])
                && ($entityFields['googleCalendarOpportunityEventSettings']['view'] ?? null)
                    === 'google-integration:views/fields/google-calendar-opportunity-event-settings'
            : !isset($entityFields['googleCalendarOpportunityEventSettings'])
    );
    $ok(
        "$entityType removed legacy googleCalendarReminderMinutes",
        is_array($entityFields) && !isset($entityFields['googleCalendarReminderMinutes'])
    );
    $ok(
        "$entityType removed legacy googleCalendarReminderMethod",
        is_array($entityFields) && !isset($entityFields['googleCalendarReminderMethod'])
    );
}

foreach (['Meeting', 'Call', 'Task', 'Opportunity'] as $entityType) {
    foreach (['detail', 'detailSmall'] as $layoutType) {
        $rLayout = $client->get("/api/v1/$entityType/layout/$layoutType");
        $ok("GET /api/v1/$entityType/layout/$layoutType → 200", $rLayout->getStatusCode() === 200,
            'code=' . $rLayout->getStatusCode());

        $layout = json_decode((string) $rLayout->getBody());

        $ok(
            "$entityType $layoutType layout has saveToGoogleCalendar",
            $layoutHasField($layout, 'saveToGoogleCalendar')
        );
        if ($entityType === 'Opportunity') {
            $ok(
                "$entityType $layoutType layout has googleCalendarOpportunityDateList",
                $layoutHasField($layout, 'googleCalendarOpportunityDateList')
            );
            $ok(
                "$entityType $layoutType layout has googleCalendarOpportunityEventSettings",
                $layoutHasField($layout, 'googleCalendarOpportunityEventSettings')
            );
            $ok(
                "$entityType $layoutType layout does not show shared Google reminder field",
                !$layoutHasField($layout, 'googleCalendarReminderMode')
            );
            $ok(
                "$entityType $layoutType layout does not show shared Google template field",
                !$layoutHasField($layout, 'googleCalendarDescriptionTemplateOverride')
            );
        } else {
            $ok(
                "$entityType $layoutType layout has googleCalendarReminderMode",
                $layoutHasField($layout, 'googleCalendarReminderMode')
            );
            $ok(
                "$entityType $layoutType layout has googleCalendarTemplate",
                $layoutHasField($layout, 'googleCalendarTemplate')
            );
            $ok(
                "$entityType $layoutType layout has googleCalendarReminders",
                $layoutHasField($layout, 'googleCalendarReminders')
            );
            $ok(
                "$entityType $layoutType layout has googleCalendarDescriptionTemplateOverride",
                $layoutHasField($layout, 'googleCalendarDescriptionTemplateOverride')
            );
        }
        $ok(
            "$entityType $layoutType layout removed legacy googleCalendarReminderMinutes",
            !$layoutHasField($layout, 'googleCalendarReminderMinutes')
        );
        $ok(
            "$entityType $layoutType layout removed legacy googleCalendarReminderMethod",
            !$layoutHasField($layout, 'googleCalendarReminderMethod')
        );
    }
}

$linkEntityDefs = $metadata->get('entityDefs.GoogleCalendarEventLink');
$linkFields = $linkEntityDefs['fields'] ?? [];
$ok('GoogleCalendarEventLink has sourceEntityType', is_array($linkFields) && isset($linkFields['sourceEntityType']));
$ok('GoogleCalendarEventLink has sourceDateType', is_array($linkFields) && isset($linkFields['sourceDateType']));
$ok('GoogleCalendarEventLink has googleEventId', is_array($linkFields) && isset($linkFields['googleEventId']));
$ok('GoogleCalendarEventLink has user link', is_array($linkFields) && isset($linkFields['user']));
$ok('Google Calendar routes file exists', is_file(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Resources/routes.json'));
$ok('Google Calendar manager frontend exists', is_file(__DIR__ . '/../client/custom/modules/google-integration/src/views/calendar/google-calendar-manager.js'));
$ok('Reusable date export panel frontend exists', is_file(__DIR__ . '/../client/custom/modules/google-integration/src/views/calendar-date-export-panel.js'));

$calendarTemplateDefs = $metadata->get('entityDefs.CalendarTemplate');
$calendarTemplateFields = $calendarTemplateDefs['fields'] ?? [];
$calendarTemplateScope = $metadata->get('scopes.CalendarTemplate') ?? [];
$ok('CalendarTemplate metadata exists', is_array($calendarTemplateFields) && isset($calendarTemplateFields['targetEntityType']));
$ok('CalendarTemplate has template fields', isset($calendarTemplateFields['summaryTemplate'], $calendarTemplateFields['descriptionTemplate'], $calendarTemplateFields['reminders']));
$ok('CalendarTemplate scope is routable BasePlus entity', ($calendarTemplateScope['entity'] ?? null) === true && ($calendarTemplateScope['type'] ?? null) === 'BasePlus' && ($calendarTemplateScope['tab'] ?? null) === true);
$ok('CalendarTemplate client icon exists', ($metadata->get(['clientDefs', 'CalendarTemplate', 'iconClass']) ?? null) === 'fas fa-calendar-check');
$rCalendarTemplateList = $client->get('/api/v1/CalendarTemplate', ['query' => ['select' => 'id,name', 'maxSize' => 1]]);
$ok('GET /api/v1/CalendarTemplate list is routable', $rCalendarTemplateList->getStatusCode() === 200, 'code=' . $rCalendarTemplateList->getStatusCode());
$rCalendarTemplateLayout = $client->get('/api/v1/CalendarTemplate/layout/detail');
$ok('GET /api/v1/CalendarTemplate/layout/detail is routable', $rCalendarTemplateLayout->getStatusCode() === 200, 'code=' . $rCalendarTemplateLayout->getStatusCode());

$calendarDateSourceDefs = $metadata->get('entityDefs.CalendarDateSource');
$calendarDateSourceFields = $calendarDateSourceDefs['fields'] ?? [];
$calendarDateSourceScope = $metadata->get('scopes.CalendarDateSource') ?? [];
$ok('CalendarDateSource metadata exists', is_array($calendarDateSourceFields) && isset($calendarDateSourceFields['targetEntityType']));
$ok('CalendarDateSource has source fields', isset($calendarDateSourceFields['dateField'], $calendarDateSourceFields['sourceDateType'], $calendarDateSourceFields['calendarViewEnabled']));
$ok(
    'CalendarDateSource targetEntityType accepts any date-capable entity (varchar)',
    ($calendarDateSourceFields['targetEntityType']['type'] ?? null) === 'varchar'
        && !isset($calendarDateSourceFields['targetEntityType']['options'])
);
$ok(
    'CalendarTemplate targetEntityType accepts any date-capable entity (varchar)',
    ($calendarTemplateFields['targetEntityType']['type'] ?? null) === 'varchar'
        && !isset($calendarTemplateFields['targetEntityType']['options'])
);
$rDateCapable = $client->get('/api/v1/GoogleIntegration/calendar/date-capable-entity-types');
$dateCapableBody = json_decode((string) $rDateCapable->getBody(), true) ?: [];
$dateCapableTypes = array_column(is_array($dateCapableBody['list'] ?? null) ? $dateCapableBody['list'] : [], 'entityType');
$dateCapableHasLabel = is_array($dateCapableBody['list'] ?? null)
    && $dateCapableBody['list'] !== []
    && is_string($dateCapableBody['list'][0]['label'] ?? null)
    && ($dateCapableBody['list'][0]['label'] ?? '') !== '';
$ok(
    'GET date-capable-entity-types returns readable list',
    $rDateCapable->getStatusCode() === 200 && $dateCapableHasLabel,
    'code=' . $rDateCapable->getStatusCode()
);
$ok(
    'date-capable-entity-types includes Meeting',
    in_array('Meeting', $dateCapableTypes, true)
);
$ok(
    'date-capable-entity-types includes more than export defaults',
    count($dateCapableTypes) > 4,
    'count=' . count($dateCapableTypes)
);
$rGoogleCalendars = $client->get('/api/v1/GoogleIntegration/calendar/google-calendars');
$googleCalendarsBody = json_decode((string) $rGoogleCalendars->getBody(), true) ?: [];
$ok(
    'GET google-calendars is routable',
    $rGoogleCalendars->getStatusCode() === 200 && is_array($googleCalendarsBody['list'] ?? null),
    'code=' . $rGoogleCalendars->getStatusCode()
);
$ok('CalendarDateSource scope is routable Base entity', ($calendarDateSourceScope['entity'] ?? null) === true && ($calendarDateSourceScope['type'] ?? null) === 'Base' && ($calendarDateSourceScope['tab'] ?? null) === true);
$ok('CalendarDateSource client icon exists', ($metadata->get(['clientDefs', 'CalendarDateSource', 'iconClass']) ?? null) === 'fas fa-calendar-day');
$ok('CalendarDateSource ACL allows admin config edits', ($metadata->get(['aclDefs', 'CalendarDateSource', 'edit']) ?? null) === 'all');
$ok('CalendarTemplate ACL allows read for template picker', ($metadata->get(['aclDefs', 'CalendarTemplate', 'read']) ?? null) === 'all');
$rTemplateOptions = $client->get('/api/v1/GoogleIntegration/calendar/template-options/Opportunity');
$templateOptionsBody = json_decode((string) $rTemplateOptions->getBody(), true) ?: [];
$templateCount = is_array($templateOptionsBody['templates'] ?? null) ? count($templateOptionsBody['templates']) : 0;
$ok(
    'GET template-options/Opportunity returns seeded templates',
    $rTemplateOptions->getStatusCode() === 200 && $templateCount > 0,
    'code=' . $rTemplateOptions->getStatusCode() . ' count=' . $templateCount
);
$templateRows = is_array($templateOptionsBody['templates'] ?? null) ? $templateOptionsBody['templates'] : [];
$oppTemplatesScoped = $templateRows === [] || array_reduce(
    $templateRows,
    static fn (bool $c, array $row): bool => $c && ($row['targetEntityType'] ?? '') === 'Opportunity',
    true
);
$ok('template-options/Opportunity scoped to Opportunity only', $oppTemplatesScoped);
$rCallTpl = $client->get('/api/v1/GoogleIntegration/calendar/template-options/Call');
$callTplBody = json_decode((string) $rCallTpl->getBody(), true) ?: [];
$callTplRows = is_array($callTplBody['templates'] ?? null) ? $callTplBody['templates'] : [];
$callScoped = $callTplRows === [] || array_reduce(
    $callTplRows,
    static fn (bool $c, array $row): bool => $c && ($row['targetEntityType'] ?? '') === 'Call',
    true
);
$ok('template-options/Call scoped to Call only', $rCallTpl->getStatusCode() === 200 && $callScoped);
$ok(
    'google-calendar-template-link field view exists',
    is_file(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/google-calendar-template-link.js')
);
$rCalendarDateSourceList = $client->get('/api/v1/CalendarDateSource', ['query' => ['select' => 'id,name', 'maxSize' => 1]]);
$ok('GET /api/v1/CalendarDateSource list is routable', $rCalendarDateSourceList->getStatusCode() === 200, 'code=' . $rCalendarDateSourceList->getStatusCode());
$rCalendarDateSourceLayout = $client->get('/api/v1/CalendarDateSource/layout/detail');
$ok('GET /api/v1/CalendarDateSource/layout/detail is routable', $rCalendarDateSourceLayout->getStatusCode() === 200, 'code=' . $rCalendarDateSourceLayout->getStatusCode());
$perDateView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/google-calendar-opportunity-event-settings.js') ?: '';
$templateView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/google-calendar-description-template.js') ?: '';
$ok('Opportunity per-date template selector is not raw ID input', !str_contains($perDateView, 'CalendarTemplate ID') && str_contains($perDateView, 'data-role="calendarTemplateId"'));
$ok('Variable picker uses shared bottom panel UI', str_contains($perDateView, 'google-integration:lib/google-calendar-variable-panel') && str_contains($templateView, 'google-integration:lib/google-calendar-variable-panel'));
$calendarView = file_get_contents('custom/Espo/Modules/GoogleIntegration/Resources/metadata/clientDefs/Calendar.json') ?: '';
$ok('Calendar view override metadata exists', str_contains($calendarView, 'google-integration:views/calendar/calendar'));
$routes = json_decode(file_get_contents('custom/Espo/Modules/GoogleIntegration/Resources/routes.json') ?: '[]', true) ?: [];
$routePaths = array_column($routes, 'route');
$ok('CRM date-source calendar route exists', in_array('/GoogleIntegration/calendar/crm-events', $routePaths, true));

foreach ([
    'Meeting:main',
    'Call:main',
    'Task:main',
    'Opportunity:presentationDate',
    'Opportunity:closeDate',
] as $sourceKey) {
    [$sourceEntityType, $sourceDateType] = explode(':', $sourceKey);
    $source = $em->getRDBRepository('CalendarDateSource')
        ->where([
            'targetEntityType' => $sourceEntityType,
            'sourceDateType' => $sourceDateType,
            'deleted' => false,
        ])
        ->findOne();
    $ok("Default CalendarDateSource $sourceKey exists", $source !== null);
}

echo "\nORM + Integration REST (API user expected 403)\n";

$gh = $em->getRDBRepository('Integration')->where(['id' => GoogleIntegrationInstaller::INTEGRATION_ID])->findOne();
$ok('Integration ' . GoogleIntegrationInstaller::INTEGRATION_ID . ' exists in database', $gh !== null);

$legacy = $em->getRDBRepository('Integration')->where(['id' => 'GoogleSafehouse'])->findOne();
$ok('Legacy Integration GoogleSafehouse removed', $legacy === null);

$rInt403 = $client->get('/api/v1/Integration/' . GoogleIntegrationInstaller::INTEGRATION_ID);
$ok(
    'API user GET Integration/' . GoogleIntegrationInstaller::INTEGRATION_ID . ' → 403 (expected for type=api)',
    $rInt403->getStatusCode() === 403,
    'code=' . $rInt403->getStatusCode()
);

echo "\nExternalAccount calendarSyncMode hook\n";

$userId = $user->getId();
$externalAccountId = GoogleIntegrationInstaller::INTEGRATION_ID . '__' . $userId;
$externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);

if ($externalAccount === null) {
    $externalAccount = $em->createEntity('ExternalAccount', [
        'id' => $externalAccountId,
        'enabled' => true,
    ]);
}

$externalAccount->set('enabled', true);
$externalAccount->clear('calendarSyncMode');
$em->saveEntity($externalAccount);
$externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);
$ok(
    'beforeSave sets default calendarSyncMode when missing',
    $externalAccount !== null && $externalAccount->get('calendarSyncMode') === 'none',
    'mode=' . (string) ($externalAccount?->get('calendarSyncMode') ?? 'null')
);

$externalAccount->set('enabled', false);
$em->saveEntity($externalAccount);
$encoded = json_encode($externalAccount->getValueMap());
$ok('disconnect save (enabled=false) produces JSON value map', $encoded !== false && $encoded !== '');
$ok('disconnect clears enabled flag', $externalAccount->get('enabled') === false);

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
