<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use PDO;
use Throwable;

/**
 * Permanently drop retired Contact-STI legacy party tables and related GCal seeds.
 * Member / VolunteerEmployee metadata is gone; empty leftover tables and
 * CalendarDateSource rows must not survive rebuild or Google Integration may
 * keep stale CDS rows around.
 *
 * NEVER drop a table that still has live rows. The only Contact STI migrator
 * (`bin/migrate-ve-member-to-contact.php`) is DDEV-only (`refuse-production`)
 * and is excluded from prod rsync. Production rebuild used to DROP
 * unconditionally, destroying unmigrated people and meeting/document links.
 *
 * @noinspection PhpUnused
 */
class DropRetiredPartyTables implements RebuildAction
{
    /** @var list<string> */
    private const TABLES = [
        'document_volunteer_employee',
        'meeting_volunteer_employee',
        'volunteer_employee',
        'member',
    ];

    /** @var list<string> */
    private const RETIRED_ENTITY_TYPES = [
        'Member',
        'VolunteerEmployee',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private Log $log
    ) {}

    /**
     * Fail-closed drop decision. Unknown counts must not destroy data.
     */
    public static function shouldDropRetiredTable(bool $tableExists, ?int $liveRowCount): bool
    {
        return $tableExists && $liveRowCount === 0;
    }

    public function process(): void
    {
        $pdo = $this->entityManager->getPDO();

        foreach (self::TABLES as $table) {
            if (!preg_match('/^[a-z0-9_]+$/', $table)) {
                continue;
            }

            if (!$this->tableExists($pdo, $table)) {
                continue;
            }

            $live = $this->liveRowCount($pdo, $table);

            if (!self::shouldDropRetiredTable(true, $live)) {
                $this->log->warning(
                    "DropRetiredPartyTables: refusing to DROP `{$table}` "
                    . '(liveRowCount=' . ($live === null ? 'unknown' : (string) $live) . '). '
                    . 'Migrate VolunteerEmployee/Member to Contact before dropping leftover party tables.'
                );

                continue;
            }

            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }

        $placeholders = implode(',', array_fill(0, count(self::RETIRED_ENTITY_TYPES), '?'));

        if ($this->tableExists($pdo, 'calendar_date_source')) {
            $stmt = $pdo->prepare(
                "UPDATE `calendar_date_source`
                 SET deleted = 1, is_active = 0
                 WHERE target_entity_type IN ({$placeholders})"
            );
            $stmt->execute(self::RETIRED_ENTITY_TYPES);
        }

        if ($this->tableExists($pdo, 'google_calendar_event_link')) {
            $stmt = $pdo->prepare(
                "UPDATE `google_calendar_event_link`
                 SET deleted = 1
                 WHERE source_entity_type IN ({$placeholders})"
            );
            $stmt->execute(self::RETIRED_ENTITY_TYPES);
        }

        $cachePath = 'data/cache/google-integration-date-source-entity-types.json';

        if (is_file($cachePath)) {
            @unlink($cachePath);
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            return false;
        }

        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table)
        );

        return $stmt !== false && (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $column)) {
            return false;
        }

        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = " . $pdo->quote($table) . "
               AND column_name = " . $pdo->quote($column)
        );

        return $stmt !== false && (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Live (not soft-deleted) row count, or null when the count cannot be trusted.
     */
    private function liveRowCount(PDO $pdo, string $table): ?int
    {
        try {
            $sql = $this->columnExists($pdo, $table, 'deleted')
                ? "SELECT COUNT(*) FROM `{$table}` WHERE `deleted` = 0"
                : "SELECT COUNT(*) FROM `{$table}`";

            $stmt = $pdo->query($sql);

            if ($stmt === false) {
                return null;
            }

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }
}
