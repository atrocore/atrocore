
Espo.define('controllers/import', 'controllers/record', function (Dep) {

    return Dep.extend({

        defaultAction: 'index',

        checkAccessGlobal: function () {
            if (this.getAcl().checkScope('Import')) {
                return true;
            }
            return false;
        },

        checkAccess: function () {
            if (this.getAcl().checkScope('Import')) {
                return true;
            }
            return false;
        },

        index: function () {
            this.main('views/import/index', null);
        }

    });

});
