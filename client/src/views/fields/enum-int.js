

Espo.define('views/fields/enum-int', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        type: 'enumInt',

        listTemplate: 'fields/enum/detail',

        detailTemplate: 'fields/enum/detail',

        editTemplate: 'fields/enum/edit',

        searchTemplate: 'fields/enum/search',

        validations: [],

        fetch: function () {
            var value = parseInt(this.$el.find('[name="' + this.name + '"]').val());
            var data = {};
            data[this.name] = value;
            return data;
        },

        parseItemForSearch: function (item) {
            return parseInt(item);
        }

    });
});

