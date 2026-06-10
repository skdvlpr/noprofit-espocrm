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
use Espo\Core\ApplicationState;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
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
$metadata->init(true);

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

echo "\nGoogle Calendar dynamic entity metadata\n";

$calendarCapableEntityTypes = [];

foreach ($em->getRDBRepository('CalendarDateSource')
    ->select(['targetEntityType'])
    ->where(['isActive' => true, 'deleted' => false])
    ->find() as $row) {
    $type = $row->get('targetEntityType');

    if (is_string($type) && $type !== '') {
        $calendarCapableEntityTypes[$type] = true;
    }
}

$calendarCapableEntityTypes = array_keys($calendarCapableEntityTypes);
sort($calendarCapableEntityTypes);

$ok(
    'at least one active CalendarDateSource target entity',
    $calendarCapableEntityTypes !== [],
    'count=' . count($calendarCapableEntityTypes)
);

foreach ($calendarCapableEntityTypes as $entityType) {
    if (!$metadata->get(['scopes', $entityType, 'entity'])) {
        continue;
    }

    $entityFields = $metadata->get(['entityDefs', $entityType, 'fields']) ?? [];
    $ok(
        "$entityType entityDefs merged in metadata cache",
        is_array($entityFields) && $entityFields !== []
    );

    $ok(
        "$entityType has saveToGoogleCalendar",
        is_array($entityFields) && isset($entityFields['saveToGoogleCalendar'])
    );
    $ok(
        "$entityType has googleCalendarDateSourceList",
        is_array($entityFields)
            && isset($entityFields['googleCalendarDateSourceList'])
            && ($entityFields['googleCalendarDateSourceList']['view'] ?? null)
                === 'google-integration:views/fields/google-calendar-date-source-list'
    );
    $ok(
        "$entityType has googleCalendarEventSettings",
        is_array($entityFields)
            && isset($entityFields['googleCalendarEventSettings'])
            && ($entityFields['googleCalendarEventSettings']['view'] ?? null)
                === 'google-integration:views/fields/google-calendar-opportunity-event-settings'
    );
    $ok(
        "$entityType has no legacy shared Google fields",
        is_array($entityFields)
            && !isset(
                $entityFields['googleCalendarReminderMode'],
                $entityFields['googleCalendarReminders'],
                $entityFields['googleCalendarOpportunityDateList'],
                $entityFields['googleCalendarOpportunityEventSettings'],
                $entityFields['googleCalendarTemplate'],
                $entityFields['googleCalendarDescriptionTemplateOverride']
            )
    );
    $ok(
        "$entityType app.layouts detail module is GoogleIntegration",
        $metadata->get(['app', 'layouts', $entityType, 'detail', 'module']) === 'GoogleIntegration'
    );

    foreach (['detail', 'detailSmall'] as $layoutType) {
        $rLayout = $client->get("/api/v1/$entityType/layout/$layoutType");

        if ($rLayout->getStatusCode() === 403) {
            $ok(
                "$entityType $layoutType layout skipped (API user has no scope access)",
                true,
                'code=403'
            );

            continue;
        }

        $ok("GET /api/v1/$entityType/layout/$layoutType → 200", $rLayout->getStatusCode() === 200,
            'code=' . $rLayout->getStatusCode());

        $layout = json_decode((string) $rLayout->getBody());

        $ok(
            "$entityType $layoutType layout has saveToGoogleCalendar",
            $layoutHasField($layout, 'saveToGoogleCalendar')
        );
        $ok(
            "$entityType $layoutType layout has googleCalendarDateSourceList",
            $layoutHasField($layout, 'googleCalendarDateSourceList')
        );
        $ok(
            "$entityType $layoutType layout has googleCalendarEventSettings",
            $layoutHasField($layout, 'googleCalendarEventSettings')
        );
        $ok(
            "$entityType $layoutType layout has no shared Google reminder fields",
            !$layoutHasField($layout, 'googleCalendarReminderMode')
                && !$layoutHasField($layout, 'googleCalendarReminders')
                && !$layoutHasField($layout, 'googleCalendarOpportunityDateList')
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
$ok('CalendarTemplate scope is routable BasePlus entity (no navbar tab)', ($calendarTemplateScope['entity'] ?? null) === true && ($calendarTemplateScope['type'] ?? null) === 'BasePlus' && ($calendarTemplateScope['tab'] ?? null) === false);
$ok('CalendarTemplate client icon exists', ($metadata->get(['clientDefs', 'CalendarTemplate', 'iconClass']) ?? null) === 'fas fa-calendar-check');
$rCalendarTemplateList = $client->get('/api/v1/CalendarTemplate', ['query' => ['select' => 'id,name', 'maxSize' => 1]]);
$ok('GET /api/v1/CalendarTemplate list is routable', $rCalendarTemplateList->getStatusCode() === 200, 'code=' . $rCalendarTemplateList->getStatusCode());
$rCalendarTemplateLayout = $client->get('/api/v1/CalendarTemplate/layout/detail');
$ok('GET /api/v1/CalendarTemplate/layout/detail is routable', $rCalendarTemplateLayout->getStatusCode() === 200, 'code=' . $rCalendarTemplateLayout->getStatusCode());

$volunteerEmployeeSaveGoogle = $metadata->get(['entityDefs', 'VolunteerEmployee', 'fields', 'saveToGoogleCalendar']);
$volunteerEmployeeDateList = $metadata->get(['entityDefs', 'VolunteerEmployee', 'fields', 'googleCalendarDateSourceList']);
$volunteerEmployeeEventSettings = $metadata->get(['entityDefs', 'VolunteerEmployee', 'fields', 'googleCalendarEventSettings']);
$ok(
    'VolunteerEmployee merged Google Calendar export fields',
    is_array($volunteerEmployeeSaveGoogle) && ($volunteerEmployeeSaveGoogle['type'] ?? null) === 'bool'
);
$ok(
    'VolunteerEmployee has googleCalendarDateSourceList',
    is_array($volunteerEmployeeDateList)
        && ($volunteerEmployeeDateList['view'] ?? null) === 'google-integration:views/fields/google-calendar-date-source-list'
);
$ok(
    'VolunteerEmployee has googleCalendarEventSettings',
    is_array($volunteerEmployeeEventSettings)
        && ($volunteerEmployeeEventSettings['view'] ?? null)
            === 'google-integration:views/fields/google-calendar-opportunity-event-settings'
);

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
$ok(
    'date-capable-entity-types includes Account',
    in_array('Account', $dateCapableTypes, true)
);
$ok(
    'date-capable-entity-types excludes InboundEmail',
    !in_array('InboundEmail', $dateCapableTypes, true)
);

$itGlobalPath = __DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Resources/i18n/it_IT/Global.json';
$itGlobal = is_readable($itGlobalPath)
    ? (json_decode((string) file_get_contents($itGlobalPath), true) ?: [])
    : [];
$ok(
    'it_IT GoogleIntegration scopeNames Account is Account (not Conti)',
    ($itGlobal['scopeNames']['Account'] ?? '') === 'Account'
);
$ok(
    'it_IT GoogleIntegration scopeNames Opportunity is Fondi e Finanziamenti',
    ($itGlobal['scopeNames']['Opportunity'] ?? '') === 'Fondi e Finanziamenti'
);
$integrationOpportunityDefault = (string) ($metadata->get([
    'integrations',
    'GoogleCalendarDrive',
    'fields',
    'googleCalendarDescriptionTemplateOpportunity',
    'default',
]) ?? '');
$ok(
    'Integration Opportunity description template default is English',
    str_contains($integrationOpportunityDefault, 'Grants & Funding')
        && !str_contains($integrationOpportunityDefault, 'Fondi e Finanziamenti')
);
$rGoogleCalendars = $client->get('/api/v1/GoogleIntegration/calendar/google-calendars');
$googleCalendarsBody = json_decode((string) $rGoogleCalendars->getBody(), true) ?: [];
$ok(
    'GET google-calendars is routable',
    $rGoogleCalendars->getStatusCode() === 200 && is_array($googleCalendarsBody['list'] ?? null),
    'code=' . $rGoogleCalendars->getStatusCode()
);
$ok('CalendarDateSource scope is routable Base entity (no navbar tab)', ($calendarDateSourceScope['entity'] ?? null) === true && ($calendarDateSourceScope['type'] ?? null) === 'Base' && ($calendarDateSourceScope['tab'] ?? null) === false);
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
$entityTypeView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/calendar-config-entity-type.js') ?: '';
$ok('Opportunity per-date template selector is not raw ID input', !str_contains($perDateView, 'CalendarTemplate ID') && str_contains($perDateView, 'data-role="calendarTemplateId"'));
$ok('Variable picker uses shared bottom panel UI', str_contains($perDateView, 'google-integration:lib/google-calendar-variable-panel') && str_contains($templateView, 'google-integration:lib/google-calendar-variable-panel'));
$ok('CalendarTemplate template field resolves target entity from select', str_contains($templateView, 'readTargetEntityTypeFromField'));
$ok('CalendarTemplate entity type select syncs initial value to model', str_contains($entityTypeView, 'syncInitialValue') || str_contains($entityTypeView, 'initialValue'));
$titleHelper = file_get_contents(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Tools/Calendar/GoogleCalendarEventTitle.php') ?: '';
$crmFetcher = file_get_contents(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Tools/Calendar/CrmDateSourceEventFetcher.php') ?: '';
$ok('Google event titles use canonical separator', str_contains($titleHelper, "SEPARATOR = ' - '") && !str_contains($crmFetcher, ' · '));
$dateSourceBeforeSave = file_get_contents(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Hooks/CalendarDateSource/BeforeSave.php') ?: '';
$dateFieldView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/calendar-config-date-field.js') ?: '';
$adminEditView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/admin/integrations/edit.js') ?: '';
$ok('CalendarDateSource blocks datetime companion date fields', str_contains($dateSourceBeforeSave, 'dateStartDate') && str_contains($dateFieldView, 'BLOCKED_DATE_FIELD_NAMES'));
$ok('Admin integration links to CalendarDateSource and CalendarTemplate', str_contains($adminEditView, '#CalendarDateSource') && str_contains($adminEditView, '#CalendarTemplate'));
$ok('Dead admin template-modal view removed', !is_file(__DIR__ . '/../client/custom/modules/google-integration/src/views/admin/integrations/template-modal.js'));
$eventSettingsView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/google-calendar-opportunity-event-settings.js') ?: '';
$templateLinkView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/google-calendar-template-link.js') ?: '';
$ok('Per-date UI uses unified date list field only', !str_contains($eventSettingsView, 'googleCalendarOpportunityDateList') && str_contains($templateLinkView, 'googleCalendarDateSourceList'));
$ok('Opportunity Google field migration script exists', is_file(__DIR__ . '/migrate-opportunity-google-calendar-fields.php'));
$ok('Location field has variable helper', str_contains($perDateView, 'variable-helper-location'));
$eventPusherSource = file_get_contents(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Tools/Calendar/EventPusher.php') ?: '';
$ok('EventPusher renders location template variables', str_contains($eventPusherSource, 'buildLocation'));
$emptyEventsPos = strpos($eventPusherSource, 'if ($events === [])');
$emptyEventsCleanupPos = strpos(
    $eventPusherSource,
    '$this->eventRemover->removeStaleDateSourceLinks($entity, $actor, []);'
);
$emptyEventsReturnPos = $emptyEventsPos === false ? false : strpos($eventPusherSource, 'return;', $emptyEventsPos);
$ok(
    'EventPusher clears stale Google links when selected date fields are empty',
    $emptyEventsPos !== false
        && $emptyEventsCleanupPos !== false
        && $emptyEventsReturnPos !== false
        && $emptyEventsCleanupPos > $emptyEventsPos
        && $emptyEventsCleanupPos < $emptyEventsReturnPos
);
$ok(
    'EventPusher ACL check uses explicit Google account owner',
    str_contains($eventPusherSource, 'use Espo\Core\AclManager;')
        && str_contains($eventPusherSource, '$this->aclManager->checkEntityEdit($actor, $entity)')
        && !str_contains($eventPusherSource, 'checkEntityEdit($entity, $actor)')
);
$eventRemoverSource = file_get_contents(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Tools/Calendar/EventRemover.php') ?: '';
$ok(
    'EventRemover ACL check uses explicit Google account owner',
    str_contains($eventRemoverSource, 'use Espo\Core\AclManager;')
        && str_contains($eventRemoverSource, '$this->aclManager->checkEntityEdit($user, $entity)')
        && !str_contains($eventRemoverSource, 'checkEntityEdit($entity, $user)')
);
$calendarSyncRunnerSource = file_get_contents(__DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Tools/Calendar/CalendarSyncRunner.php') ?: '';
$aclCheckPos = strpos($calendarSyncRunnerSource, '$this->aclManager->checkEntityEdit($user, $entity)');
$saveEntityPos = strpos($calendarSyncRunnerSource, '$this->entityManager->saveEntity($entity)');
$ok(
    'CalendarSyncRunner checks edit ACL before Google-to-CRM writes',
    str_contains($calendarSyncRunnerSource, 'use Espo\Core\AclManager;')
        && $aclCheckPos !== false
        && $saveEntityPos !== false
        && $aclCheckPos < $saveEntityPos
);
$ok(
    'CalendarSyncRunner updates Task dateStart on every Google pull',
    str_contains(
        $calendarSyncRunnerSource,
        "if (\$entityType === 'Task') {\n            \$entity->set('dateStart', \$start);\n            \$entity->set('dateEnd', \$end);"
    )
        && !str_contains($calendarSyncRunnerSource, "\$entity->get('dateStart') === null")
);
$callDetailLayout = file_get_contents(
    __DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Resources/layouts/Call/detail.json'
) ?: '';
$ok(
    'Call detail layout includes core fields (name, dateStart)',
    str_contains($callDetailLayout, '"name"') && str_contains($callDetailLayout, '"dateStart"')
);
$dateSourceListView = file_get_contents(__DIR__ . '/../client/custom/modules/google-integration/src/views/fields/google-calendar-date-source-list.js') ?: '';
$ok('Per-date settings loads date sources from API', str_contains($perDateView, 'date-source-options'));
$ok('Date source list field loads options from API', str_contains($dateSourceListView, 'date-source-options'));
$routes = json_decode(file_get_contents('custom/Espo/Modules/GoogleIntegration/Resources/routes.json') ?: '[]', true) ?: [];
$routePaths = array_column($routes, 'route');
$ok('date-source-options route exists', in_array('/GoogleIntegration/calendar/date-source-options/:entityType', $routePaths, true));
$ok('template-form route exists', in_array('/GoogleIntegration/calendar/template-form/:templateId', $routePaths, true));
$calendarView = file_get_contents('custom/Espo/Modules/GoogleIntegration/Resources/metadata/clientDefs/Calendar.json') ?: '';
$ok('Calendar view override metadata exists', str_contains($calendarView, 'google-integration:views/calendar/calendar'));
$ok('CRM date-source calendar route exists', in_array('/GoogleIntegration/calendar/crm-events', $routePaths, true));

foreach ([
    'Meeting:main',
    'Call:main',
    'Task:main',
    'Opportunity:presentationDate',
    'Opportunity:closeDate',
    'VolunteerEmployee:main',
    'VolunteerEmployee:endDate',
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

echo "\nCalendarSyncRunner ExternalAccount id* prefix\n";

$integrationPrefix = GoogleIntegrationInstaller::INTEGRATION_ID . '__';
$prefixMatchCount = $em->getRDBRepository('ExternalAccount')
    ->where(['id*' => $integrationPrefix . '%', 'enabled' => true])
    ->count();
$brokenPrefixCount = $em->getRDBRepository('ExternalAccount')
    ->where(['id*' => $integrationPrefix, 'enabled' => true])
    ->count();
$ok(
    'ExternalAccount id* uses LIKE prefix with trailing %',
    $prefixMatchCount >= 1,
    "prefix+% count=$prefixMatchCount"
);
$ok(
    'ExternalAccount id* without % does not match user rows (Espo LIKE semantics)',
    $brokenPrefixCount === 0,
    "prefix-only count=$brokenPrefixCount"
);

echo "\nGoogle Calendar DB columns for calendar-capable entities\n";

foreach ($calendarCapableEntityTypes as $entityType) {
    $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityType) ?? $entityType);
    $pdo = $em->getPDO();
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'save_to_google_calendar'");
    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
    $ok(
        "$entityType table has save_to_google_calendar column",
        is_array($row) && ($row['Field'] ?? '') === 'save_to_google_calendar',
        'table=' . $table
    );
}

echo "\nCanonical sourceDateType + link lookup\n";

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);
$dateSourceProvider = $injectableFactory->create(DateSourceProvider::class);

echo "\nEventPusher date source defaulting\n";

try {
    $eventPusherForDates = $injectableFactory->create(\Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher::class);
    $getSelected = new ReflectionMethod($eventPusherForDates, 'getSelectedDateSourceTypes');
    $getSelected->setAccessible(true);

    $sources = [
        ['sourceDateType' => 'main', 'dateField' => 'startDate', 'allDay' => true],
        ['sourceDateType' => 'endDate', 'dateField' => 'endDate', 'allDay' => true],
    ];

    $emptyListEntity = $em->getNewEntity('VolunteerEmployee');
    $emptyListEntity->set([
        'id' => Util::generateId(),
        'name' => 'Smoke GCal date default',
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => [],
        'startDate' => '2026-06-01',
        'endDate' => '2026-12-31',
    ]);

    $defaulted = $getSelected->invoke($eventPusherForDates, $emptyListEntity, $sources);

    $ok(
        'getSelectedDateSourceTypes defaults to all allowed when list empty',
        $defaulted === ['main', 'endDate'],
        'got=' . implode(',', $defaulted)
    );

    $unsetListEntity = $em->getNewEntity('VolunteerEmployee');
    $unsetListEntity->set([
        'saveToGoogleCalendar' => true,
        'startDate' => '2026-06-01',
        'endDate' => '2026-12-31',
    ]);

    $defaultedUnset = $getSelected->invoke($eventPusherForDates, $unsetListEntity, $sources);

    $ok(
        'getSelectedDateSourceTypes defaults when list unset',
        $defaultedUnset === ['main', 'endDate'],
        'got=' . implode(',', $defaultedUnset)
    );

    $explicitEntity = $em->getNewEntity('VolunteerEmployee');
    $explicitEntity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => ['main'],
        'startDate' => '2026-06-01',
        'endDate' => '2026-12-31',
    ]);

    $explicit = $getSelected->invoke($eventPusherForDates, $explicitEntity, $sources);

    $ok(
        'getSelectedDateSourceTypes respects explicit subset',
        $explicit === ['main'],
        'got=' . implode(',', $explicit)
    );

    $buildEvents = new ReflectionMethod($eventPusherForDates, 'buildCalendarDateSourceGoogleEvents');
    $buildEvents->setAccessible(true);
    $built = $buildEvents->invoke($eventPusherForDates, $emptyListEntity, $sources);

    $ok(
        'buildCalendarDateSourceGoogleEvents builds events when date list empty',
        count($built) === 2,
        'count=' . count($built)
    );
} catch (Throwable $e) {
    $ok('EventPusher date source defaulting smoke', false, $e->getMessage());
}

$ok(
    'Meeting canonical main stays main',
    $dateSourceProvider->canonicalSourceDateType('Meeting', 'main') === 'main'
);

$memberSources = $dateSourceProvider->getActiveSourcesForEntityType('Member');

if ($memberSources !== []) {
    $memberFirstKey = (string) ($memberSources[0]['sourceDateType'] ?? 'main');
    $ok(
        'Member legacy main maps to first active date key',
        $dateSourceProvider->canonicalSourceDateType('Member', 'main') === $memberFirstKey,
        'firstKey=' . $memberFirstKey
    );
    $ok(
        'Member explicit date key is unchanged',
        $dateSourceProvider->canonicalSourceDateType('Member', $memberFirstKey) === $memberFirstKey
    );
} else {
    $ok('Member legacy main maps to first active date key', true, 'skipped (no Member CalendarDateSource)');
    $ok('Member explicit date key is unchanged', true, 'skipped (no Member CalendarDateSource)');
}

$canonicalLinkEntityId = substr(Util::generateId(), 0, 17);
$canonicalUserId = $container->getByClass(ApplicationState::class)->getUser()->getId();

try {
    $legacyLink = $em->createEntity('GoogleCalendarEventLink', [
        'sourceEntityType' => 'Meeting',
        'sourceEntityId' => $canonicalLinkEntityId,
        'sourceDateType' => 'main',
        'userId' => $canonicalUserId,
        'calendarId' => 'primary',
        'googleEventId' => 'smoke-canonical-legacy-event',
        'name' => 'Meeting:' . $canonicalLinkEntityId . ':main:' . $canonicalUserId,
    ]);
    $em->saveEntity($legacyLink);

    $canonical = $dateSourceProvider->canonicalSourceDateType('Meeting', 'main');
    $matchedLinks = [];

    foreach (
        $em->getRDBRepository('GoogleCalendarEventLink')
            ->where([
                'sourceEntityType' => 'Meeting',
                'sourceEntityId' => $canonicalLinkEntityId,
                'userId' => $canonicalUserId,
                'deleted' => false,
            ])
            ->find() as $link
    ) {
        $linkCanonical = $dateSourceProvider->canonicalSourceDateType(
            'Meeting',
            (string) ($link->get('sourceDateType') ?? '')
        );

        if ($linkCanonical === $canonical) {
            $matchedLinks[] = $link;
        }
    }

    $ok(
        'canonical link lookup finds legacy main row',
        count($matchedLinks) === 1,
        'matched=' . count($matchedLinks)
    );

    $eventPusher = $injectableFactory->create(\Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher::class);
    $findLink = new ReflectionMethod($eventPusher, 'findLink');
    $findLink->setAccessible(true);
    $meeting = $em->getNewEntity('Meeting');
    $meeting->set('id', $canonicalLinkEntityId);
    $resolvedLink = $findLink->invoke($eventPusher, $meeting, 'main');

    $ok(
        'EventPusher findLink resolves canonical Meeting main',
        $resolvedLink !== null && $resolvedLink->getId() === $legacyLink->getId(),
        'resolved=' . (string) ($resolvedLink?->getId() ?? 'null')
    );

    $saveLink = new ReflectionMethod($eventPusher, 'saveLink');
    $saveLink->setAccessible(true);
    $saveLink->invoke(
        $eventPusher,
        $meeting,
        'main',
        'primary',
        'smoke-canonical-updated-event',
        null
    );

    $persistedLink = $em->getEntityById('GoogleCalendarEventLink', $legacyLink->getId());

    $ok(
        'saveLink keeps single canonical row and updates googleEventId',
        $persistedLink !== null
            && $persistedLink->get('googleEventId') === 'smoke-canonical-updated-event'
            && $persistedLink->get('sourceDateType') === 'main',
        'eventId=' . (string) ($persistedLink?->get('googleEventId') ?? 'null')
    );

    $em->removeEntity($legacyLink);
} catch (Throwable $e) {
    $ok('canonical link lookup smoke', false, $e->getMessage());
}

echo "\nDelete sync + background job\n";

$ok(
    'EventRemover class exists',
    class_exists(\Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover::class)
);
$ok(
    'SyncCalendar job class exists',
    class_exists(\Espo\Modules\GoogleIntegration\Jobs\SyncCalendar::class)
);
$syncJobMeta = $metadata->get(['app', 'scheduledJobs', 'GoogleIntegrationSyncCalendar']) ?? [];
$ok(
    'scheduledJobs GoogleIntegrationSyncCalendar registered',
    is_array($syncJobMeta)
        && str_contains((string) ($syncJobMeta['jobClassName'] ?? ''), 'SyncCalendar')
);

$smokeSourceEntityId = substr(Util::generateId(), 0, 17);

try {
    $deleteLink = $em->createEntity('GoogleCalendarEventLink', [
        'sourceEntityType' => 'Meeting',
        'sourceEntityId' => $smokeSourceEntityId,
        'sourceDateType' => 'main',
        'userId' => $userId,
        'calendarId' => 'work-calendar-smoke@test',
        'googleEventId' => 'smoke-fake-google-event-id',
        'name' => 'Meeting:' . $smokeSourceEntityId . ':main:' . $userId,
    ]);
    $em->saveEntity($deleteLink);

    /** @var InjectableFactory $injectableFactory */
    $injectableFactory = $container->getByClass(InjectableFactory::class);
    $eventRemover = $injectableFactory->create(\Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover::class);
    $eventRemover->removeLink($deleteLink);

    $deletedLink = $em->getEntityById('GoogleCalendarEventLink', $deleteLink->getId());
    $ok(
        'EventRemover removes link row when Google client unavailable',
        $deletedLink === null
    );
} catch (Throwable $e) {
    $ok('EventRemover ORM smoke', false, $e->getMessage());
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
