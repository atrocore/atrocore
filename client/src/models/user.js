
Espo.define('models/user', 'model', function (Dep) {

    return Dep.extend({

        name: 'User',

        isAdmin: function () {
            return this.get('isAdmin');
        },

        isPortal: function () {
            return this.get('isPortalUser');
        }
    });
});
