

Espo.define('acl/email', 'acl', function (Dep) {

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
        },

        checkIsOwner: function (model) {
            if (this.getUser().id === model.get('assignedUserId') || this.getUser().id === model.get('createdById')) {
                return true;
            }

            if (!model.has('assignedUsersIds')) {
                return null;
            }

            if (~(model.get('assignedUsersIds') || []).indexOf(this.getUser().id)) {
                return true;
            }

            return false;
        },

        checkModelDelete: function (model, data, precise) {
            var result = this.checkModel(model, data, 'delete', precise);

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

            if (model.get('createdById') === this.getUser().id) {
                if (model.get('status') !== 'Sent' && model.get('status') !== 'Archived') {
                    return true;
                }
            }

            return result;
        }
    });
});
