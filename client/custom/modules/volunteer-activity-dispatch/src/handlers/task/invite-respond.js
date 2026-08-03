define('volunteer-activity-dispatch:handlers/task/invite-respond', [], function () {

    class Handler {
        constructor(view) {
            this.view = view;
            this._status = null;
            this._loaded = false;
        }

        isAcceptVisible() {
            this.ensureLoaded();

            return this._status === 'Assigned';
        }

        isDeclineVisible() {
            this.ensureLoaded();

            return ['Assigned', 'Confirmed'].includes(this._status);
        }

        ensureLoaded() {
            if (this._loaded || !this.view.model || !this.view.model.id) {
                return;
            }

            this._loaded = true;
            this._status = null;

            Espo.Ajax
                .getRequest('ActivityInvite', {
                    maxSize: 1,
                    select: 'id,status',
                    where: [
                        {type: 'equals', attribute: 'taskId', value: this.view.model.id},
                        {type: 'equals', attribute: 'userId', value: this.view.getUser().id},
                    ],
                })
                .then(response => {
                    this._status = (response.list && response.list.length) ?
                        response.list[0].status : null;

                    this.view.reRender();
                })
                .catch(() => {
                    this._status = null;
                });
        }

        accept() {
            this.respond('Confirmed', 'acceptSuccess');
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
                    this._status = status;
                    model.fetch();
                })
                .catch(() => {
                    Espo.Ui.error(view.translate('Error occurred'));
                });
        }
    }

    return Handler;
});
