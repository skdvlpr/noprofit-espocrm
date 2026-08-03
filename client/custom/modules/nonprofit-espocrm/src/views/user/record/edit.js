define('nonprofit-espocrm:views/user/record/edit', [
    'views/user/record/edit',
    'nonprofit-espocrm:views/user/record/role-profile-mixin',
], function (Dep, Mixin) {

    return Dep.extend(Object.assign({}, Mixin, {

        setup() {
            Dep.prototype.setup.call(this);
            this.setupRoleProfileFlags();
        },
    }));
});
