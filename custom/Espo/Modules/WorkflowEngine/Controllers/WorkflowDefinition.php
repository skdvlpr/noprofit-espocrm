<?php

namespace Espo\Modules\WorkflowEngine\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\WorkflowEngine\Services\WorkflowRunner;
use stdClass;

class WorkflowDefinition extends Record
{
    /**
     * Manual run: POST WorkflowDefinition/action/run
     * Body: { id, targetId [, triggerType=manual] }
     */
    public function postActionRun(Request $request): stdClass
    {
        if (!$this->acl->check('WorkflowDefinition', 'edit')) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        $id = isset($body->id) ? (string) $body->id : '';
        $targetId = isset($body->targetId) ? (string) $body->targetId : '';
        $requestedTrigger = isset($body->triggerType) ? (string) $body->triggerType : 'manual';

        if ($id === '' || $targetId === '') {
            throw new BadRequest("Params 'id' and 'targetId' are required.");
        }

        if (!in_array($requestedTrigger, ['manual'], true)) {
            throw new BadRequest("triggerType must be 'manual'.");
        }

        $definition = $this->entityManager->getEntityById('WorkflowDefinition', $id);

        if (!$definition) {
            throw new NotFound();
        }

        if (!$definition->get('isActive')) {
            throw new Error('Workflow definition is not active.');
        }

        if ((string) $definition->get('triggerType') !== $requestedTrigger) {
            throw new BadRequest(
                "Definition triggerType is '{$definition->get('triggerType')}', not '{$requestedTrigger}'."
            );
        }

        $targetEntityType = (string) $definition->get('targetEntityType');

        if ($targetEntityType === '') {
            throw new Error('Workflow definition has no targetEntityType.');
        }

        $target = $this->entityManager->getEntityById($targetEntityType, $targetId);

        if (!$target) {
            throw new NotFound("Target {$targetEntityType}#{$targetId} not found.");
        }

        if (!$this->acl->check($targetEntityType, 'read') || !$this->acl->check($target, 'read')) {
            throw new Forbidden("No read access to target {$targetEntityType}.");
        }

        /** @var WorkflowRunner $runner */
        $runner = $this->injectableFactory->create(WorkflowRunner::class);
        $runner->runDefinitionOnEntity($definition, $target);

        return (object) [
            'ok' => true,
            'workflowDefinitionId' => $id,
            'targetEntityType' => $targetEntityType,
            'targetId' => $targetId,
            'triggerType' => $requestedTrigger,
        ];
    }
}
