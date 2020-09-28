

Espo.define('views/fields/link-category-tree', 'views/fields/link', function (Dep) {

    return Dep.extend({

        selectRecordsView: 'views/modals/select-category-tree-records',

        autocompleteDisabled: false,

        fetchSearch: function () {
            var data = Dep.prototype.fetchSearch.call(this);

            if (!data) return data;

            if (data.typeFront == 'is') {
                data.field = this.name;
                data.type = 'inCategory';
            }
            return data;
        },
    });
});

