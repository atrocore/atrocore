

Espo.define('acl-portal/email', 'acl-portal', function (Dep) {

    return Dep.extend({

        checkModelRead: function (model, data, precise) {
            var result = this.checkModel(model, data, 'read', precise);

            if (result) {
                return true;
            }

            if (data === false) {
                return false;
            }

            var d = data || {};
            if (d.read === 'no') {
                return false;
            }

            if (model.has('usersIds')) {
                if (~(model.get('usersIds') || []).indexOf(this.getUser().id)) {
                    return true;
                }
            } else {
                if (precise) {
                    return null;
                }
            }

            return result;
        }

    });

});

