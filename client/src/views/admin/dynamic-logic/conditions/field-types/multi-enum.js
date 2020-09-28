

Espo.define('views/admin/dynamic-logic/conditions/field-types/multi-enum', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field);
            }

            return item;
        },

        getValueViewName: function () {
            var viewName = Dep.prototype.getValueViewName.call(this);

            if (~['has', 'notHas'].indexOf(this.type)) {
                viewName = 'views/fields/enum';
            }

            return viewName;
        },

    });

});

