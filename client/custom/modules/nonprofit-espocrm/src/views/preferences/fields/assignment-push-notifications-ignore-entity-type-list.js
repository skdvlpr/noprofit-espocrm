define('nonprofit-espocrm:views/preferences/fields/assignment-push-notifications-ignore-entity-type-list', [
    'views/fields/checklist'
], function (Dep) {

    /**
     * Mirror of Espo in-app assignment ignore checklist, for Web Push.
     * isInversed: checked in UI = enabled (not stored in ignore list).
     */
    return Dep.extend({

        isInversed: true,

        setupOptions: function () {
            this.params.options = Espo.Utils.clone(
                this.getConfig().get('assignmentNotificationsEntityList')
            ) || [];
        },
    });
});
