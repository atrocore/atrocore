

Espo.define('views/admin/auth-log-record/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        rowActionsView: 'views/record/row-actions/view-and-remove',

        massActionList: ['remove'],

        checkAllResultMassActionList: ['remove']

    });
});
