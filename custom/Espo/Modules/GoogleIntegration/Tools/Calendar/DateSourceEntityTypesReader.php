<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Reads active CalendarDateSource target entity types without bootstrapping Application.
 */
class DateSourceEntityTypesReader
{
    private const CACHE_RELATIVE = 'data/cache/google-integration-date-source-entity-types.json';

    /**
     * @return list<string>
     */
    public function readActiveTargetEntityTypes(): array
    {
        $fromDb = $this->readFromDatabase();

        if ($fromDb !== null) {
            $this->writeCache($fromDb);

            return $fromDb;
        }

        return $this->readCache();
    }

    public function writeCacheFromDatabase(): void
    {
        $fromDb = $this->readFromDatabase();

        if ($fromDb !== null) {
            $this->writeCache($fromDb);
        }
    }

    /**
     * @return list<string>|null
     */
    private function readFromDatabase(): ?array
    {
        $config = $this->loadConfig();

        if ($config === null) {
            return null;
        }

        $platform = strtolower((string) ($config['database']['platform'] ?? $config['database']['driver'] ?? ''));

        try {
            $pdo = $this->createPdo($config, $platform);
        } catch (PDOException) {
            return null;
        }

        $table = $this->resolveTableName($config, $platform, 'calendar_date_source');

        $sql = "SELECT DISTINCT target_entity_type AS targetEntityType
            FROM {$table}
            WHERE deleted = 0 AND is_active = 1
                AND target_entity_type IS NOT NULL AND target_entity_type != ''";

        try {
            $statement = $pdo->query($sql);
        } catch (PDOException) {
            return null;
        }

        if ($statement === false) {
            return null;
        }

        $entityTypes = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['targetEntityType'] ?? $row['target_entity_type'] ?? null;

            if (is_string($type) && $type !== '') {
                $entityTypes[$type] = true;
            }
        }

        $list = array_keys($entityTypes);
        sort($list);

        return array_values($list);
    }

    /**
     * @return list<string>
     */
    private function readCache(): array
    {
        $path = $this->installRoot() . '/' . self::CACHE_RELATIVE;

        if (!is_readable($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path) ?: '[]', true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($item): bool => is_string($item) && $item !== ''));
    }

    /**
     * @param list<string> $entityTypes
     */
    private function writeCache(array $entityTypes): void
    {
        $path = $this->installRoot() . '/' . self::CACHE_RELATIVE;
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create cache directory: ' . $dir);
        }

        file_put_contents($path, json_encode(array_values($entityTypes), JSON_PRETTY_PRINT) . "\n");
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 6);
    }

    private function installRoot(): string
    {
        foreach ($this->installRootCandidates() as $candidate) {
            if (is_readable($candidate . '/data/config.php')) {
                return $candidate;
            }
        }

        return $this->projectRoot();
    }

    /**
     * @return list<string>
     */
    private function installRootCandidates(): array
    {
        $candidates = [];
        $projectRoot = $this->projectRoot();

        $candidates[] = $projectRoot;
        $candidates[] = $projectRoot . '/build/test';

        $cwd = getcwd();

        if ($cwd !== false) {
            $candidates[] = rtrim(str_replace('\\', '/', $cwd), '/');
        }

        $envRoot = getenv('ESPOCRM_INSTALL_ROOT');

        if ($envRoot !== false && $envRoot !== '') {
            $candidates[] = rtrim(str_replace('\\', '/', $envRoot), '/');
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadConfig(): ?array
    {
        $root = $this->installRoot();
        $mainPath = $root . '/data/config.php';

        if (!is_readable($mainPath)) {
            return null;
        }

        /** @var array<string, mixed> $config */
        $config = include $mainPath;

        $internalPath = $root . '/data/config-internal.php';

        if (is_readable($internalPath)) {
            /** @var array<string, mixed> $internal */
            $internal = include $internalPath;
            $config = array_replace_recursive($config, $internal);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createPdo(array $config, string $platform): PDO
    {
        $host = (string) ($config['database']['host'] ?? 'localhost');
        $port = (string) ($config['database']['port'] ?? '');
        $dbname = (string) ($config['database']['dbname'] ?? $config['database']['database'] ?? '');
        $user = (string) ($config['database']['user'] ?? '');
        $password = (string) ($config['database']['password'] ?? '');
        $charset = (string) ($config['database']['charset'] ?? 'utf8mb4');

        if (in_array($platform, ['postgresql', 'postgres', 'pdo_pgsql'], true)) {
            $dsn = 'pgsql:host=' . $host
                . ($port !== '' ? ';port=' . $port : '')
                . ';dbname=' . $dbname;

            return new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }

        $dsn = 'mysql:host=' . $host
            . ($port !== '' ? ';port=' . $port : '')
            . ';dbname=' . $dbname
            . ';charset=' . $charset;

        return new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveTableName(array $config, string $platform, string $entityName): string
    {
        $prefix = (string) ($config['database']['prefix'] ?? '');
        $name = $prefix . $this->toSnakeCase($entityName);

        if (in_array($platform, ['postgresql', 'postgres', 'pdo_pgsql'], true)) {
            return '"' . str_replace('"', '""', $name) . '"';
        }

        return $name;
    }

    private function toSnakeCase(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value) ?? $value;

        return strtolower($value);
    }
}
