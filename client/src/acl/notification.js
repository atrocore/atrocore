

Espo.define('acl/notification', 'acl', function (Dep) {

    return Dep.extend({

        checkIsOwner: function (model) {
            if (this.getUser().id === model.get('userId')) {
                return true;
            }
            return false;
        }
    });
});
