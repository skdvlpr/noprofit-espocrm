define('workflow-engine:handlers/workflow-definition-actions', ['action-handler'], function (Dep) {

    return Dep.extend({

        runManual: function (data, view) {
            const model = view.model;
            const triggerType = model.get('triggerType');

            if (triggerType !== 'manual') {
                Espo.Ui.warning(
                    view.translate('runManualOnly', 'messages', 'WorkflowDefinition')
                );

                return;
            }

            const targetId = window.prompt(
                view.translate('runManualNeedTargetId', 'messages', 'WorkflowDefinition'),
                ''
            );

            if (!targetId) {
                return;
            }

            Espo.Ajax.postRequest('WorkflowDefinition/action/run', {
                id: model.id,
                targetId: String(targetId).trim(),
                triggerType: triggerType,
            }).then(() => {
                Espo.Ui.success(
                    view.translate('runManualDone', 'messages', 'WorkflowDefinition')
                );
            });
        },
    });
});
