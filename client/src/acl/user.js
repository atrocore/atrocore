

Espo.define('acl/user', 'acl', function (Dep) {

    return Dep.extend({

        checkIsOwner: function (model) {
            return this.getUser().id === model.id;
        }

    });
});
