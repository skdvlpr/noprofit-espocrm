define('volunteer-activity-dispatch:handlers/activity-offer/publish', [], function () {

    class Handler {
        constructor(view) {
            this.view = view;
        }

        isPublishVisible() {
            const model = this.view.model;

            if (!model || model.get('status') !== 'Draft') {
                return false;
            }

            return this.view.getAcl().check(model, 'edit');
        }

        publish() {
            const view = this.view;
            const model = view.model;

            Espo.Ui.confirm(
                view.translate('publishConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Publish week', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/publish', {id: model.id})
                    .then(response => {
                        const msg = view
                            .translate('publishSuccess', 'messages', 'ActivityOffer')
                            .replace('{taskCount}', String(response.taskCount ?? 0))
                            .replace('{inviteCount}', String(response.inviteCount ?? 0))
                            .replace('{notifyCount}', String(response.notifyCount ?? 0));

                        Espo.Ui.success(msg);
                        model.fetch();
                    })
                    .catch(() => {
                        Espo.Ui.error(view.translate('Error occurred'));
                    });
            });
        }
    }

    return Handler;
});
