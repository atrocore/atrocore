

Espo.define('views/admin/label-manager/category', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/label-manager/category',

        data: function () {
            return {
                categoryDataList: this.getCategotyDataList()
            };
        },

        events: {

        },

        setup: function () {
            this.scope = this.options.scope;
            this.language = this.options.language;
            this.categoryData = this.options.categoryData;
        },

        getCategotyDataList: function () {
            var labelList = Object.keys(this.categoryData);

            labelList.sort(function (v1, v2) {
                return v1.localeCompare(v2);
            }.bind(this));

            var categoryDataList = [];

            labelList.forEach(function (name) {
                var value = this.categoryData[name];

                if (value === null) {
                    value = '';
                }

                if (value.replace) {
                    value = value.replace(/\n/i, '\\n');
                }
                var o = {
                    name: name,
                    value: value
                };
                var arr = name.split('[.]');

                var label = arr.slice(1).join(' . ');

                o.label = label;
                categoryDataList.push(o);
            }, this);

            return categoryDataList;
        }

    });
});


