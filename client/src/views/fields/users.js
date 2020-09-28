

Espo.define('views/fields/users', 'views/fields/link-multiple', function (Dep) {

    return Dep.extend({

        init: function () {
            this.assignmentPermission = this.getAcl().get('assignmentPermission');
            if (this.assignmentPermission == 'no') {
                this.readOnly = true;
            }
            Dep.prototype.init.call(this);
        },

        getSelectBoolFilterList: function () {
            if (this.assignmentPermission == 'team' || this.assignmentPermission == 'no') {
                return ['onlyMyTeam'];
            }
        },

        getSelectPrimaryFilterName: function () {
            return 'active';
        }

    });
});


