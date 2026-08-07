define('bug-tracker:views/admin/bug-tracker', ['views/settings/record/edit'], function (Dep) {

    return Dep.extend({

        layoutName: 'bugTracker',

        saveAndContinueEditingAction: false,
    });
});
