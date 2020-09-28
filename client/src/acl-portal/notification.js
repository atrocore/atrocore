

Espo.define('acl-portal/notification', 'acl-portal', function (Dep) {

    return Dep.extend({

        checkIsOwner: function (model) {
            if (this.getUser().id === model.get('userId')) {
                return true;
            }
            return false;
        }

    });
});
