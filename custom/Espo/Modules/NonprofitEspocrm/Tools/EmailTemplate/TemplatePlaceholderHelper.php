<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate;

use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Builds CRM deep-links and applies {recordUrl} / {EntityType.recordUrl} /
 * {link.recordUrl} tokens. Missing values become empty strings (no throw).
 */
class TemplatePlaceholderHelper
{
    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
    ) {}

    public function urlFor(?Entity $entity): string
    {
        if (!$entity || !$entity->hasId()) {
            return '';
        }

        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');

        if ($siteUrl === '') {
            return '';
        }

        return $siteUrl . '/#' . $entity->getEntityType() . '/view/' . $entity->getId();
    }

    /**
     * @param array<string, Entity> $entityHash keys like ActivityOffer, User, Parent, Account
     */
    public function applyRecordUrls(string $text, ?Entity $primary = null, array $entityHash = []): string
    {
        if ($text === '' || !str_contains($text, '{')) {
            return $text;
        }

        $primaryUrl = $this->urlFor($primary);

        $text = str_replace(
            ['{recordUrl}', '{planUrl}'],
            [$primaryUrl, $primaryUrl],
            $text
        );

        if ($primary) {
            $type = $primary->getEntityType();
            $text = str_replace(
                ['{' . $type . '.recordUrl}', '{Parent.recordUrl}'],
                [$primaryUrl, $primaryUrl],
                $text
            );

            $text = $this->applyLinkRecordUrls($text, $primary, $type);
        }

        foreach ($entityHash as $key => $entity) {
            if (!is_string($key) || !$entity instanceof Entity) {
                continue;
            }

            $url = $this->urlFor($entity);
            $text = str_replace('{' . $key . '.recordUrl}', $url, $text);
        }

        // Any remaining *.recordUrl tokens → empty (related missing / unknown scope).
        $cleared = preg_replace('/\{[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)*\.recordUrl\}/', '', $text);
        $text = is_string($cleared) ? $cleared : $text;

        $clearedBare = preg_replace('/\{recordUrl\}|\{planUrl\}/', '', $text);

        return is_string($clearedBare) ? $clearedBare : $text;
    }

    /**
     * Espo leaves `{Entity.field}` intact when the value is null. Replace leftovers
     * with empty string so templates never show raw tokens.
     */
    public function clearUnresolvedEntityPlaceholders(string $text): string
    {
        if ($text === '' || !str_contains($text, '{')) {
            return $text;
        }

        // {Entity.field} or {Entity.link.field} — keep single-token placeholders
        // like {today}, {optOutUrl}, {shiftList}.
        $cleared = preg_replace(
            '/\{[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+\}/',
            '',
            $text
        );

        return is_string($cleared) ? $cleared : $text;
    }

    private function applyLinkRecordUrls(string $text, Entity $entity, string $entityType): string
    {
        if (!preg_match_all(
            '/\{(?:' . preg_quote($entityType, '/') . '|Parent)\.([a-zA-Z][a-zA-Z0-9_]*)\.recordUrl\}/',
            $text,
            $matches
        )) {
            // Also support bare {account.recordUrl} when `account` is a link on primary.
            if (!preg_match_all('/\{([a-z][a-zA-Z0-9_]*)\.recordUrl\}/', $text, $matches2)) {
                return $text;
            }

            foreach (array_unique($matches2[1]) as $link) {
                $url = $this->urlFor($this->findRelated($entity, $link));
                $text = str_replace('{' . $link . '.recordUrl}', $url, $text);
            }

            return $text;
        }

        foreach (array_unique($matches[1]) as $link) {
            $url = $this->urlFor($this->findRelated($entity, $link));
            $text = str_replace(
                [
                    '{' . $entityType . '.' . $link . '.recordUrl}',
                    '{Parent.' . $link . '.recordUrl}',
                ],
                [$url, $url],
                $text
            );
        }

        return $text;
    }

    private function findRelated(Entity $entity, string $link): ?Entity
    {
        try {
            if ($entity->hasRelation($link)) {
                $related = $this->entityManager
                    ->getRDBRepository($entity->getEntityType())
                    ->getRelation($entity, $link)
                    ->findOne();

                if ($related) {
                    return $related;
                }
            }

            $id = $entity->get($link . 'Id');

            if (!is_string($id) || $id === '') {
                return null;
            }

            $relatedType = $entity->getRelationParam($link, 'entity')
                ?? $entity->getAttributeParam($link . 'Id', 'entity');

            if (!is_string($relatedType) || $relatedType === '') {
                return null;
            }

            return $this->entityManager->getEntityById($relatedType, $id);
        } catch (\Throwable) {
            return null;
        }
    }
}
