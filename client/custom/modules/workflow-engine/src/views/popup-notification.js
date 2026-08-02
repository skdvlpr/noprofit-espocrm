define('workflow-engine:views/popup-notification', ['views/popup-notification'], function (Dep) {

    return Dep.extend({

        template: 'workflow-engine:popup-notification',

        type: 'workflowMessage',

        style: 'primary',

        closeButton: true,

        collapseButton: true,

        data: function () {
            const data = Dep.prototype.data.call(this);
            const nd = this.notificationData || {};

            return {
                ...data,
                header: this.translate('CreateNotification', 'options', 'WorkflowDefinition') ||
                    this.translate('Notification'),
                message: nd.message || '',
                relatedType: nd.relatedType || null,
                relatedId: nd.relatedId || null,
                relatedName: nd.relatedName || nd.relatedId || '',
                hasRelated: !!(nd.relatedType && nd.relatedId),
            };
        },

        onCancel: function () {
            if (!this.notificationId) {
                return;
            }

            Espo.Ajax.putRequest('Notification/' + this.notificationId, {read: true})
                .catch(() => {});
        },

        getTitle: function () {
            const nd = this.notificationData || {};

            if (nd.message) {
                return String(nd.message).substring(0, 80);
            }

            return this.translate('Notification');
        },
    });
});
