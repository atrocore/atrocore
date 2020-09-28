

Espo.define('views/user/modals/detail', 'views/modals/detail', function (Dep) {

    return Dep.extend({

        getScope: function () {
            if (this.model.get('isPortalUser')) {
                return 'PortalUser';
            }
            return 'User';
        }

    });
});

