

Espo.define('views/admin/dynamic-logic/conditions-string/item-value-enum', 'views/admin/dynamic-logic/conditions-string/item-base', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions-string/item-base',

        createValueFieldView: function () {
            var key = this.getValueViewKey();

            var viewName = 'views/fields/enum';

            this.createView('value', viewName, {
                model: this.model,
                name: this.field,
                el: this.getSelector() + '[data-view-key="'+key+'"]',
                params: {
                    options: this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'options']) || []
                }
            });
        },

    });

});

