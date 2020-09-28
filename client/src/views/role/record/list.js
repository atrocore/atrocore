

Espo.define('views/role/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

    	quickDetailDisabled: true,

        quickEditDisabled: true,

        massActionList: ['remove'],

        checkAllResultDisabled: true

    });

});

