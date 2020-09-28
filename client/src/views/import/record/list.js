

Espo.define('views/import/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

    	quickDetailDisabled: true,

        quickEditDisabled: true,

        checkAllResultDisabled: true,

        massActionList: ['remove'],

        rowActionsView: 'views/record/row-actions/remove-only'

    });
});

