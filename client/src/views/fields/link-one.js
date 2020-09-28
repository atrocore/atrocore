

Espo.define('views/fields/link-one', 'views/fields/link', function (Dep) {

    return Dep.extend({

        readOnly: true,

        searchTypeList: ['is', 'isOneOf'],

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();
            var value = this.$el.find('[name="' + this.idName + '"]').val();

            if (type == 'isOneOf') {
                var data = {
                    type: 'linkedWith',
                    field: this.name,
                    value: this.searchData.oneOfIdList,
                    oneOfIdList: this.searchData.oneOfIdList,
                    oneOfNameHash: this.searchData.oneOfNameHash,
                    data: {
                        type: type
                    }
                };
                return data;

            } else {
                if (!value) {
                    return false;
                }
                var data = {
                    type: 'linkedWith',
                    field: this.name,
                    value: value,
                    valueName: this.$el.find('[name="' + this.nameName + '"]').val(),
                    data: {
                        type: type
                    }
                };
                return data;
            }
        },

    });
});

