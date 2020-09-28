
Espo.define('controllers/team', 'controllers/record', function (Dep) {

    return Dep.extend({

        checkAccess: function (action) {
            if (action == 'read') {
                return true;
            }
            if (this.getUser().isAdmin()) {
                return true;
            }
        }

    });
});

