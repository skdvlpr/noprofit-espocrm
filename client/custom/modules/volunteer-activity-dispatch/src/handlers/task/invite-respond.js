define('volunteer-activity-dispatch:handlers/task/invite-respond', [], function () {

    class Handler {
        constructor(view) {
            this.view = view;
            this._pending = null;
            this._loaded = false;
        }

        isAcceptVisible() {
            return this.isPendingInvite();
        }

        isDeclineVisible() {
            return this.isPendingInvite();
        }

        isPendingInvite() {
            this.ensureLoaded();

            return this._pending === true;
        }

        ensureLoaded() {
            if (this._loaded || !this.view.model || !this.view.model.id) {
                return;
            }

            this._loaded = true;

            Espo.Ajax
                .getRequest('ActivityInvite', {
                    maxSize: 1,
                    select: 'id,status',
                    where: [
                        {type: 'equals', attribute: 'taskId', value: this.view.model.id},
                        {type: 'equals', attribute: 'userId', value: this.view.getUser().id},
                        {type: 'equals', attribute: 'status', value: 'Pending'},
                    ],
                })
                .then(response => {
                    this._pending = !!(response.list && response.list.length);
                    this.view.reRender();
                })
                .catch(() => {
                    this._pending = false;
                });
        }

        accept() {
            this.respond('Accepted', 'acceptSuccess');
        }

        decline() {
            this.respond('Declined', 'declineSuccess');
        }

        respond(status, messageKey) {
            const view = this.view;
            const model = view.model;

            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('Task/action/respondInvite', {id: model.id, status: status})
                .then(() => {
                    Espo.Ui.success(view.translate(messageKey, 'messages', 'ActivityInvite'));
                    this._pending = false;
                    model.fetch();
                })
                .catch(() => {
                    Espo.Ui.error(view.translate('Error occurred'));
                });
        }
    }

    return Handler;
});
