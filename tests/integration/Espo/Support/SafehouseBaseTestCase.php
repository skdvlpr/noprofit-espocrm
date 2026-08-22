<?php

declare(strict_types=1);

namespace tests\integration\Espo\Support;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\ORM\Entity;
use tests\integration\Core\BaseTestCase;

/**
 * Base for Safehouse module integration tests on isolated build/test + db_test.
 */
abstract class SafehouseBaseTestCase extends BaseTestCase
{
    protected function afterStartApplication(): void
    {
        if (class_exists(\Espo\Modules\NonprofitEspocrm\Tools\Installer::class)) {
            $this->runPostInstallSafely(\Espo\Modules\NonprofitEspocrm\Tools\Installer::class);
        }

        if (class_exists(\Espo\Modules\WorkflowEngine\Tools\Installer::class)) {
            $this->runPostInstallSafely(\Espo\Modules\WorkflowEngine\Tools\Installer::class);
        }

        if (class_exists(\Espo\Modules\GoogleIntegration\Tools\Installer::class)) {
            $this->runPostInstallSafely(\Espo\Modules\GoogleIntegration\Tools\Installer::class);
            $this->ensureGoogleCalendarDateSources();
        }

        if (class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->runPostInstallSafely(\Espo\Modules\BugTracker\Tools\Installer::class);
        }

        if (class_exists(\Espo\Modules\SafehouseAuroraThemes\Tools\Installer::class)) {
            $this->runPostInstallSafely(
                \Espo\Modules\SafehouseAuroraThemes\Tools\Installer::class
            );
        }

        $this->getConfig()->update();
        $this->getMetadata()->init(true);
    }

    /**
     * Post-install hooks may trigger secondary rebuilds that fail on a fresh db_test
     * install; entity behaviour under test does not depend on them.
     */
    private function runPostInstallSafely(string $installerClass): void
    {
        try {
            (new $installerClass())->runPostInstall($this->getContainer());
        } catch (\Throwable) {
            // Best-effort: core install + metadata init below are enough for hook tests.
        }
    }

    /**
     * Google calendar export fields are injected via metadata only for entity types with
     * active CalendarDateSource rows. Post-install may fail on a cold db_test install, or
     * stop after seeding rows but before the metadata rebuild that exposes the fields.
     */
    private function ensureGoogleCalendarDateSources(): void
    {
        if (!class_exists(\Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateSourceDefaults::class)) {
            return;
        }

        $em = $this->getEntityManager();
        $metadata = $this->getMetadata();

        if (!$metadata->get(['scopes', 'CalendarDateSource', 'entity'])) {
            return;
        }

        if ($metadata->get(['entityDefs', 'Meeting', 'fields', 'saveToGoogleCalendar'])) {
            return;
        }

        $hasActive = $em->getRDBRepository('CalendarDateSource')
            ->where(['deleted' => false, 'isActive' => true])
            ->count() > 0;

        if (!$hasActive) {
            foreach (\Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateSourceDefaults::sources() as $source) {
                $targetEntityType = $source['targetEntityType'] ?? '';

                if (
                    !is_string($targetEntityType)
                    || $targetEntityType === ''
                    || !$metadata->get(['scopes', $targetEntityType, 'entity'])
                ) {
                    continue;
                }

                $existing = $em->getRDBRepository('CalendarDateSource')
                    ->where([
                        'targetEntityType' => $targetEntityType,
                        'sourceDateType' => $source['sourceDateType'],
                        'deleted' => false,
                    ])
                    ->findOne();

                if ($existing !== null) {
                    continue;
                }

                $em->saveEntity($em->createEntity('CalendarDateSource', array_merge([
                    'isActive' => true,
                    'calendarViewEnabled' => true,
                ], $source)));
            }
        }

        (new \Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceEntityTypesReader())
            ->writeCacheFromDatabase();

        try {
            $this->getDataManager()->rebuild();
        } catch (\Throwable) {
            // Rebuild can fail on a partial install; metadata init below is still attempted.
        }

        $metadata->init(true);
    }

    protected function uniqueMarker(): string
    {
        return 'phpunit-' . bin2hex(random_bytes(4));
    }

    /**
     * @param callable(): void $action
     * @param string ...$needles Substrings expected in the exception message (any match).
     */
    protected function assertBadRequest(callable $action, string ...$needles): void
    {
        try {
            $action();
        } catch (BadRequest $e) {
            if ($needles === []) {
                $this->assertTrue(true);

                return;
            }

            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($e->getMessage(), $needle)) {
                    $this->assertTrue(true);

                    return;
                }
            }

            $this->fail('BadRequest message did not match expected needles: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->fail('Expected BadRequest, got ' . get_class($e) . ': ' . $e->getMessage());
        }

        $this->fail('Expected BadRequest was not thrown.');
    }

    /**
     * @param callable(): void $action
     */
    protected function assertForbidden(callable $action): void
    {
        try {
            $action();
        } catch (Forbidden $e) {
            $this->assertTrue(true);

            return;
        } catch (\Throwable $e) {
            $this->fail('Expected Forbidden, got ' . get_class($e) . ': ' . $e->getMessage());
        }

        $this->fail('Expected Forbidden was not thrown.');
    }

    protected function getAdminUser(): User
    {
        $em = $this->getEntityManager();
        $admin = $em->getRDBRepository(User::ENTITY_TYPE)
            ->where(['userName' => 'admin', 'deleted' => false])
            ->findOne();

        if ($admin === null) {
            $admin = $this->createUser([
                'userName' => 'admin',
                'firstName' => 'Admin',
                'lastName' => 'PHPUnit',
                'type' => User::TYPE_ADMIN,
                'isActive' => true,
            ]);
        }

        $this->assertNotNull($admin);

        return $admin;
    }

    protected function authenticateAsAdmin(): void
    {
        $this->authenticate('admin');
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function newPrimaNotaManual(array $extra = []): Entity
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $row = $em->getNewEntity('PrimaNota');
        $row->set(array_merge([
            'description' => 'PHPUnit manual ' . $marker,
            'entryType' => 'Income',
            'internalClassification' => 'Donation',
            'donationPaymentProvider' => 'Other',
            'donationPaymentReference' => 'PHPUNIT-MANUAL-' . $marker,
            'amountGross' => 100.0,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
        ], $extra));

        return $row;
    }
}
