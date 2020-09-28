

Espo.define('views/fields/link-multiple-category-tree', 'views/fields/link-multiple', function (Dep) {

    return Dep.extend({

        selectRecordsView: 'views/modals/select-category-tree-records',

        autocompleteDisabled: false,

        fetchSearch: function () {
            var data = Dep.prototype.fetchSearch.call(this);

            if (!data) return data;

            data.type = 'inCategory';

            return data;
        },
    });
});

