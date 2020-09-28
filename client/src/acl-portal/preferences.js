

Espo.define('acl-portal/preferences', 'acl-portal', function (Dep) {

    return Dep.extend({

        checkIsOwner: function (model) {
            if (this.getUser().id === model.id) {
                return true;
            }

            return false;
        }

    });

});

