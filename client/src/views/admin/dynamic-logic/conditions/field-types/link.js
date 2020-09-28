

Espo.define('views/admin/dynamic-logic/conditions/field-types/link', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field + 'Id',
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field + 'Id');

                var values = {};
                values[this.field + 'Name'] = this.model.get(this.field + 'Name');
                item.data.values = values;
            }

            return item;
        }

    });

});

