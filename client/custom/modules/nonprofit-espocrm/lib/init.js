(function () {
    Espo.loader.requirePromise('helpers/model/defaults-populator')
        .then(DefaultsPopulator => {
            DefaultsPopulator.prototype.toFillAssignedUser = function () {
                if (this.user.isPortal()) {
                    return false;
                }

                return true;
            };
        });
})();
