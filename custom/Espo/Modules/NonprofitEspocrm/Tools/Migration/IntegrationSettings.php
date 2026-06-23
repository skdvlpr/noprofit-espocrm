<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Migration;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\EntityManager;

/**
 * Portable export/import of app-level integration settings (Integration entity
 * rows + their config.integrations enabled flag).
 *
 * Scope and safety:
 *   - Moves APP-LEVEL settings only: client id/secret, enabled flag, and the
 *     non-secret integration fields declared in metadata `integrations.*.fields`.
 *   - Does NOT move per-user OAuth tokens (ExternalAccount). Each user
 *     re-authorizes on the target instance — tokens are instance/redirect
 *     specific and must never be copied (GCal-005).
 *   - No raw SQL: Integration rows via EntityManager, flag via ConfigWriter.
 *
 * This is a pure-logic helper so it can be unit-smoked without mutating config.
 */
class IntegrationSettings
{
    public const FORMAT_VERSION = 1;

    /**
     * Build the portable settings structure for every integration that has a
     * metadata definition and a stored Integration row.
     *
     * @return array{version:int, integrations: array<string, array{enabled:bool, configFlag:bool, fields: array<string, mixed>}>}
     */
    public static function collect(EntityManager $em, Config $config, Metadata $metadata): array
    {
        $defs = $metadata->get(['integrations']) ?? [];
        $configIntegrations = self::configIntegrations($config);

        $out = [];
        foreach (array_keys($defs) as $name) {
            $entity = $em->getEntityById(IntegrationEntity::ENTITY_TYPE, $name);
            if ($entity === null) {
                continue;
            }
            $fieldDefs = $metadata->get(['integrations', $name, 'fields']) ?? [];
            $fields = [];
            foreach (array_keys($fieldDefs) as $field) {
                $fields[$field] = $entity->get($field);
            }
            $out[$name] = [
                'enabled' => (bool) $entity->get('enabled'),
                'configFlag' => (bool) ($configIntegrations[$name] ?? false),
                'fields' => $fields,
            ];
        }

        return ['version' => self::FORMAT_VERSION, 'integrations' => $out];
    }

    /**
     * Compute the changes that importing $incoming would make over $current.
     * Returns a per-integration list of changed keys with masked values.
     *
     * @param array<string, mixed> $current  Output of collect().
     * @param array<string, mixed> $incoming Parsed settings file.
     * @return array<string, array<int, string>>
     */
    public static function diff(array $current, array $incoming): array
    {
        $changes = [];
        $curInt = $current['integrations'] ?? [];
        $incInt = $incoming['integrations'] ?? [];

        foreach ($incInt as $name => $inc) {
            $cur = $curInt[$name] ?? ['enabled' => null, 'configFlag' => null, 'fields' => []];
            $lines = [];

            if ((bool) ($inc['enabled'] ?? false) !== (bool) ($cur['enabled'] ?? false)) {
                $lines[] = sprintf('enabled: %s -> %s', self::b($cur['enabled'] ?? null), self::b($inc['enabled'] ?? null));
            }
            if ((bool) ($inc['configFlag'] ?? false) !== (bool) ($cur['configFlag'] ?? false)) {
                $lines[] = sprintf('configFlag: %s -> %s', self::b($cur['configFlag'] ?? null), self::b($inc['configFlag'] ?? null));
            }
            foreach (($inc['fields'] ?? []) as $field => $value) {
                $before = ($cur['fields'] ?? [])[$field] ?? null;
                if ($before !== $value) {
                    $lines[] = sprintf(
                        '%s: %s -> %s',
                        $field,
                        self::maskValue($field, $before),
                        self::maskValue($field, $value)
                    );
                }
            }

            if ($lines !== []) {
                $changes[$name] = $lines;
            }
        }

        return $changes;
    }

    /**
     * Apply $incoming to the live instance. Idempotent: re-applying the same
     * file produces no further changes.
     *
     * @param array<string, mixed> $incoming
     * @return array<string, array<int, string>> Applied changes (masked).
     */
    public static function apply(EntityManager $em, Config $config, ConfigWriter $configWriter, Metadata $metadata, array $incoming): array
    {
        $current = self::collect($em, $config, $metadata);
        $changes = self::diff($current, $incoming);
        if ($changes === []) {
            return [];
        }

        $configIntegrations = self::configIntegrations($config);

        foreach (($incoming['integrations'] ?? []) as $name => $inc) {
            if (!isset($changes[$name])) {
                continue;
            }
            $entity = $em->getEntityById(IntegrationEntity::ENTITY_TYPE, $name);
            if ($entity === null) {
                $entity = $em->getNewEntity(IntegrationEntity::ENTITY_TYPE);
                $entity->set('id', $name);
            }
            $entity->set('enabled', (bool) ($inc['enabled'] ?? false));
            foreach (($inc['fields'] ?? []) as $field => $value) {
                $entity->set($field, $value);
            }
            $em->saveEntity($entity);

            $configIntegrations[$name] = (bool) ($inc['configFlag'] ?? false);
        }

        $configWriter->set('integrations', (object) $configIntegrations);
        $configWriter->save();

        return $changes;
    }

    /** Redact secret-ish values for any console/log output. */
    public static function maskValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '∅';
        }
        if (self::isSecretField($field)) {
            return '***';
        }
        if (is_bool($value)) {
            return self::b($value);
        }
        $s = is_scalar($value) ? (string) $value : json_encode($value);
        $s = (string) $s;
        return strlen($s) > 48 ? substr($s, 0, 45) . '…' : $s;
    }

    public static function isSecretField(string $field): bool
    {
        $f = strtolower($field);
        return str_contains($f, 'secret') || str_contains($f, 'password') || str_contains($f, 'token');
    }

    /** @return array<string, mixed> */
    private static function configIntegrations(Config $config): array
    {
        $raw = $config->get('integrations');
        if (is_object($raw)) {
            return get_object_vars($raw);
        }
        if (is_array($raw)) {
            return $raw;
        }
        return [];
    }

    private static function b(mixed $v): string
    {
        return $v ? 'true' : 'false';
    }
}
