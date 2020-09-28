

Espo.define('views/email-account/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

    	quickDetailDisabled: true,

        quickEditDisabled: true,

        checkAllResultDisabled: true,

        massActionList: ['remove', 'massUpdate'],

    });
});

