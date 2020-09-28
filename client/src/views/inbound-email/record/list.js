

Espo.define('views/inbound-email/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

    	quickDetailDisabled: true,

        quickEditDisabled: true,

        massActionList: ['remove', 'massUpdate'],

        checkAllResultDisabled: true

    });

});

