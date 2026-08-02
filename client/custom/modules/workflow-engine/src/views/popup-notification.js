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

        // Core skips the sound when the popup comes from the first poll after
        // page load (isFirstCheck). For workflow messages we still want a sound
        // if the notification is genuinely fresh.
        onShow: function () {
            if (this.options.isFirstCheck && !this.isFreshNotification()) {
                return;
            }

            this.playSound();
        },

        isFreshNotification: function () {
            const createdAt = (this.notificationData || {}).createdAt;

            if (!createdAt) {
                return false;
            }

            const createdMs = Date.parse(String(createdAt).replace(' ', 'T') + 'Z');

            if (isNaN(createdMs)) {
                return false;
            }

            return Date.now() - createdMs < 120 * 1000;
        },

        // Core does not handle the browser autoplay policy: Audio.play() may
        // reject if there was no user gesture yet. Retry once on the next click.
        playSound: function () {
            if (!this.getPreferences().get('notificationSound')) {
                return;
            }

            const audioElement = new Audio(this.soundPath);
            audioElement.volume = 0.3;

            const promise = audioElement.play();

            if (promise && typeof promise.catch === 'function') {
                promise.catch(() => {
                    document.addEventListener('click', () => {
                        audioElement.play().catch(() => {});
                    }, {once: true});
                });
            }
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
