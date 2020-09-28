

Espo.define('views/list-tree', 'views/list', function (Dep) {

    return Dep.extend({

        searchPanel: false,

        createButton: false,

        name: 'listTree',

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.listTree') || 'views/record/list-tree';
        }

    });
});

