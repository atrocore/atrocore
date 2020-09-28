

Espo.define('views/admin/dynamic-logic/conditions-string/item-multiple-values-base', 'views/admin/dynamic-logic/conditions-string/item-base', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions-string/item-multiple-values-base',

        data: function () {
            return {
                valueViewDataList: this.valueViewDataList,
                scope: this.scope,
                operator: this.operator,
                operatorString: this.operatorString,
                field: this.field
            };
        },

        populateValues: function () {
        },


        getValueViewKey: function (i) {
            return 'view-' + this.level.toString() + '-' + this.number.toString() + '-' + i.toString();
        },

        createValueFieldView: function () {
            var valueList = this.itemData.value || [];

            var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'type']) || 'base';
            var viewName = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'view']) || this.getFieldManager().getViewName(fieldType);

            this.valueViewDataList = [];
            valueList.forEach(function (value, i) {
                var model = this.model.clone();
                model.set(this.itemData.attribute, value);

                var key = this.getValueViewKey(i);
                this.valueViewDataList.push({
                    key: key,
                    isEnd: i === valueList.length - 1
                });

                this.createView(key, viewName, {
                    model: model,
                    name: this.field,
                    el: this.getSelector() + ' [data-view-key="'+key+'"]'
                });
            }, this);
        },

    });

});

