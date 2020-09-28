

Espo.define('views/admin/dynamic-logic/conditions-string/item-base', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions-string/item-base',

        data: function () {
            return {
                valueViewKey: this.getValueViewKey(),
                scope: this.scope,
                operator: this.operator,
                operatorString: this.operatorString,
                field: this.field
            };
        },

        setup: function () {
            this.itemData = this.options.itemData;

            this.level = this.options.level || 0;
            this.number = this.options.number || 0;
            this.scope = this.options.scope;

            this.operator = this.options.operator || this.operator;
            this.operatorString = this.options.operatorString || this.operatorString;

            this.additionalData = (this.itemData.data || {});

            this.field = (this.itemData.data || {}).field || this.itemData.attribute;

            this.wait(true);

            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;

                this.populateValues();

                this.createValueFieldView();

                this.wait(false);
            }, this);
        },

        populateValues: function () {
            if (this.itemData.attribute) {
                this.model.set(this.itemData.attribute, this.itemData.value);
            }
            this.model.set(this.additionalData.values || {});
        },

        getValueViewKey: function () {
            return 'view-' + this.level.toString() + '-' + this.number.toString() + '-0';
        },

        createValueFieldView: function () {
            var key = this.getValueViewKey();

            var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'type']) || 'base';
            var viewName = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'view']) || this.getFieldManager().getViewName(fieldType);

            this.createView('value', viewName, {
                model: this.model,
                name: this.field,
                el: this.getSelector() + ' [data-view-key="'+key+'"]'
            });
        },

    });

});

