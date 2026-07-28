<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\PrimaNotaStatsProvider;

class GetPrimaNotaSummary implements Action
{
    public function __construct(
        private PrimaNotaStatsProvider $primaNotaStatsProvider,
        private Metadata $metadata,
        private Acl $acl,
    ) {}

    public function process(Request $request): Response
    {
        $additionalWhere = $this->resolveRelatedWhere(
            $request->getQueryParam('parentType'),
            $request->getQueryParam('parentId'),
            $request->getQueryParam('link'),
        );

        return ResponseComposer::json(
            $this->primaNotaStatsProvider->getSummary(null, $additionalWhere)
        );
    }

    /**
     * Scope period banner to a parent relationship panel
     * (Contact/Account Related payments, Opportunity Movimenti, …).
     *
     * @return array<string, mixed>|null
     */
    private function resolveRelatedWhere(
        ?string $parentType,
        ?string $parentId,
        ?string $link,
    ): ?array {
        $parentType = is_string($parentType) ? trim($parentType) : '';
        $parentId = is_string($parentId) ? trim($parentId) : '';
        $link = is_string($link) ? trim($link) : '';

        if ($parentType === '' && $parentId === '' && $link === '') {
            return null;
        }

        if ($parentType === '' || $parentId === '' || $link === '') {
            throw new BadRequest('parentType, parentId and link are required together.');
        }

        if (!$this->acl->check($parentType, Table::ACTION_READ)) {
            throw new Forbidden("No read access to {$parentType}.");
        }

        if (!$this->acl->check('PrimaNota', Table::ACTION_READ)) {
            throw new Forbidden('No read access to PrimaNota.');
        }

        $linkDefs = $this->metadata->get(['entityDefs', $parentType, 'links', $link]) ?? [];

        if (!is_array($linkDefs) || ($linkDefs['entity'] ?? null) !== 'PrimaNota') {
            throw new BadRequest("Link {$parentType}.{$link} is not a PrimaNota relation.");
        }

        $foreign = $linkDefs['foreign'] ?? null;

        if (!is_string($foreign) || $foreign === '') {
            throw new BadRequest("Link {$parentType}.{$link} has no foreign field.");
        }

        $foreignType = $this->metadata->get(['entityDefs', 'PrimaNota', 'links', $foreign, 'type']);

        // Contact/Account Related payments → belongsToParent (subjectParty / beneficiaryParty)
        if ($foreignType === 'belongsToParent') {
            return [
                'AND' => [
                    [$foreign . 'Id' => $parentId],
                    [$foreign . 'Type' => $parentType],
                ],
            ];
        }

        // Opportunity Movimenti → belongsTo financing
        if ($foreignType === 'belongsTo') {
            $foreignEntity = $this->metadata->get(['entityDefs', 'PrimaNota', 'links', $foreign, 'entity']);

            if (is_string($foreignEntity) && $foreignEntity !== '' && $foreignEntity !== $parentType) {
                throw new BadRequest(
                    "PrimaNota.{$foreign} targets {$foreignEntity}, not {$parentType}."
                );
            }

            return [
                $foreign . 'Id' => $parentId,
            ];
        }

        throw new BadRequest(
            "PrimaNota.{$foreign} link type '{$foreignType}' is not supported for scoped summary."
        );
    }
}
