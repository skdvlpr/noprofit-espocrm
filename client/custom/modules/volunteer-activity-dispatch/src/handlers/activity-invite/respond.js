define('volunteer-activity-dispatch:handlers/activity-invite/respond', [], function () {

    class Handler {
        constructor(view) {
            this.view = view;
        }

        isPendingForMe() {
            const model = this.view.model;
            const userId = this.view.getUser().id;

            if (!model || model.get('status') !== 'Pending') {
                return false;
            }

            return model.get('userId') === userId || this.view.getUser().isAdmin();
        }

        accept() {
            this.respond('accept', 'acceptSuccess');
        }

        decline() {
            this.respond('decline', 'declineSuccess');
        }

        respond(action, messageKey) {
            const view = this.view;
            const model = view.model;

            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityInvite/action/' + action, {id: model.id})
                .then(() => {
                    Espo.Ui.success(view.translate(messageKey, 'messages', 'ActivityInvite'));
                    model.fetch();
                })
                .catch(() => {
                    Espo.Ui.error(view.translate('Error occurred'));
                });
        }
    }

    return Handler;
});
