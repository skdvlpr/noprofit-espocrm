<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\Core\Htmlizer\Htmlizer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Renders templates with Htmlizer + related path vars like {{account.name}}.
 * Inspired by GoogleIntegration EventPusher::resolveRelatedTemplateVariables (no WhatsApp).
 */
class TemplateRenderer
{
    public function __construct(
        private EntityManager $entityManager,
        private LoggerInterface $log,
    ) {}

    public function render(Entity $entity, string $template, Htmlizer $htmlizer): string
    {
        if ($template === '') {
            return '';
        }

        $template = $this->resolveRelatedPathVariables($entity, $template);

        try {
            return trim($htmlizer->render($entity, $template));
        } catch (Throwable $e) {
            $this->log->warning(
                'WorkflowEngine template render failed: {message}',
                ['message' => $e->getMessage()]
            );

            return $template;
        }
    }

    private function resolveRelatedPathVariables(Entity $entity, string $template): string
    {
        $resolved = preg_replace_callback(
            '/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/',
            function (array $matches) use ($entity): string {
                $link = $matches[1];
                $field = $matches[2];

                try {
                    if (!$entity->hasRelation($link) && !$entity->hasAttribute($link . 'Id')) {
                        return '';
                    }

                    $related = null;

                    if ($entity->hasRelation($link)) {
                        $related = $this->entityManager
                            ->getRDBRepository($entity->getEntityType())
                            ->getRelation($entity, $link)
                            ->findOne();
                    }

                    if (!$related) {
                        $id = $entity->get($link . 'Id');

                        if (!is_string($id) || $id === '') {
                            return '';
                        }

                        $entityType = $entity->getRelationParam($link, 'entity')
                            ?? $entity->getAttributeParam($link . 'Id', 'entity')
                            ?? null;

                        if (!is_string($entityType) || $entityType === '') {
                            return '';
                        }

                        $related = $this->entityManager->getEntityById($entityType, $id);
                    }

                    if (!$related) {
                        return '';
                    }

                    $value = $related->get($field);

                    if ($value === null || is_array($value) || is_object($value)) {
                        return '';
                    }

                    return (string) $value;
                } catch (Throwable $e) {
                    $this->log->debug(
                        'WorkflowEngine related var skipped: {message}',
                        ['message' => $e->getMessage()]
                    );

                    return '';
                }
            },
            $template
        );

        return is_string($resolved) ? $resolved : $template;
    }
}
