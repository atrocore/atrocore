

Espo.define('views/fields/assigned-user', 'views/fields/user-with-avatar', function (Dep) {

    return Dep.extend({

        init: function () {
            this.assignmentPermission = this.getAcl().get('assignmentPermission');
            if (this.assignmentPermission == 'no') {
                this.setReadOnly(true);
            }
            Dep.prototype.init.call(this);
        },

        getSelectBoolFilterList: function () {
            if (this.assignmentPermission == 'team') {
                return ['onlyMyTeam'];
            }
        },

        getSelectPrimaryFilterName: function () {
            return 'active';
        }

    });
});

