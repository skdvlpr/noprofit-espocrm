define('volunteer-activity-dispatch:handlers/activity-invite/respond', [], function () {

    class Handler {
        constructor(view) {
            this.view = view;
        }

        isMine() {
            const model = this.view.model;

            if (!model) {
                return false;
            }

            return model.get('userId') === this.view.getUser().id ||
                this.view.getUser().isAdmin();
        }

        isAcceptVisible() {
            return this.isMine() &&
                this.view.model.get('status') === 'Assigned';
        }

        isDeclineVisible() {
            return this.isMine() &&
                ['Available', 'Assigned', 'Confirmed'].includes(this.view.model.get('status'));
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
