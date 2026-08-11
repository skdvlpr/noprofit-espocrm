<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\ORM\EntityManager;
use PDO;

/**
 * Permanently drop retired Contact-STI legacy party tables and related GCal seeds.
 * Member / VolunteerEmployee metadata is gone; tables and CalendarDateSource rows
 * must not survive rebuild or Google Integration will rehydrate stub entityDefs.
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
        private EntityManager $entityManager
    ) {}

    public function process(): void
    {
        $pdo = $this->entityManager->getPDO();

        foreach (self::TABLES as $table) {
            if (!preg_match('/^[a-z0-9_]+$/', $table)) {
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
}
