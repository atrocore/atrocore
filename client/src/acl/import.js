

Espo.define('acl/import', 'acl', function (Dep) {

    return Dep.extend({

        checkScope: function (data, action, precise, entityAccessData) {
            return !!data;
        },

        checkModelRead: function (model, data, precise) {
            return true;
        },

        checkIsOwner: function (model) {
            if (this.getUser().id === model.get('createdById')) {
                return true;
            }

            return false;
        },

        checkModelDelete: function (model, data, precise) {
            return true;
        }

    });
});
