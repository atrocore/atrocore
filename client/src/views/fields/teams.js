

Espo.define('views/fields/teams', 'views/fields/link-multiple', function (Dep) {

    return Dep.extend({

        init: function () {
            this.assignmentPermission = this.getAcl().get('assignmentPermission');
            Dep.prototype.init.call(this);
        },

        getSelectBoolFilterList: function () {
            if (this.assignmentPermission == 'team' || this.assignmentPermission == 'no') {
                return ['onlyMy'];
            }
        },

    });
});
