

Espo.define('acl/preferences', 'acl', function (Dep) {

    return Dep.extend({

        checkIsOwner: function (model) {
            if (this.getUser().id === model.id) {
                return true;
            }
            return false;
        }

    });

});

