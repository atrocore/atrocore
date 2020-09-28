

Espo.define('views/portal-user/list', 'views/list', function (Dep) {

    return Dep.extend({

        getCreateAttributes: function () {
            return {
                isPortalUser: true
            };
        }

    });
});

