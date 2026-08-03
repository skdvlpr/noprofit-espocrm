define('nonprofit-espocrm:views/user/record/detail', [
    'views/user/record/detail',
    'nonprofit-espocrm:views/user/record/role-profile-mixin',
], function (Dep, Mixin) {

    return Dep.extend(Object.assign({}, Mixin, {

        setup() {
            Dep.prototype.setup.call(this);
            this.setupRoleProfileFlags();
        },
    }));
});
