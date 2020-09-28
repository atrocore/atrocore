

Espo.define('controllers/portal-role', 'controllers/record', function (Dep) {

    return Dep.extend({

        checkAccess: function () {
            if (this.getUser().isAdmin()) {
                return true;
            }
            return false;
        }

    });

});
