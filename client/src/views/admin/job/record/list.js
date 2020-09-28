

Espo.define('views/admin/job/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        rowActionsView: 'views/record/row-actions/view-and-remove',

        massActionList: ['remove'],

        rowActionsColumnWidth: '5%',

    });
});

