

Espo.define('views/admin/dynamic-logic/conditions/field-types/base', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions/field-types/base',

        data: function () {
            return {
                type: this.type,
                field: this.field,
                scope: this.scope,
                typeList: this.typeList
            };
        },

        events: {
            'click > div > div > [data-action="remove"]': function (e) {
                e.stopPropagation();
                this.trigger('remove-item');
            }
        },

        setup: function () {
            this.type = this.options.type;
            this.field = this.options.field;
            this.scope = this.options.scope;
            this.fieldType = this.options.fieldType;

            this.itemData = this.options.itemData;
            this.additionalData = (this.itemData.data || {});

            this.typeList = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', this.fieldType, 'typeList']);

            this.wait(true);
            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;
                this.populateValues();

                this.manageValue();
                this.wait(false);
            }, this);
        },

        afterRender: function () {
            this.$type = this.$el.find('select[data-name="type"]');
            this.$type.on('change', function () {
                this.type = this.$type.val();
                this.manageValue();
            }.bind(this));
        },

        populateValues: function () {
            if (this.itemData.attribute) {
                this.model.set(this.itemData.attribute, this.itemData.value);
            }
            this.model.set(this.additionalData.values || {});
        },

        getValueViewName: function () {
            var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'type']) || 'base';
            var viewName = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'view']) || this.getFieldManager().getViewName(fieldType);

            return viewName;
        },

        getValueFieldName: function () {
            return this.field;
        },

        manageValue: function () {
            var valueType = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'conditionTypes', this.type, 'valueType']);

            if (valueType === 'field') {
                var viewName = this.getValueViewName();
                var fieldName = this.getValueFieldName();
                this.createView('value', viewName, {
                    model: this.model,
                    name: fieldName,
                    el: this.getSelector() + ' .value-container',
                    mode: 'edit',
                    readOnlyDisabled: true
                }, function (view) {
                    if (this.isRendered()) {
                        view.render();
                    }
                }, this);

            } else if (valueType === 'custom') {
                this.clearView('value');
                var methodName = 'createValueView' + Espo.Utils.upperCaseFirst(this.type);
                this[methodName]();
            } else {
                this.clearView('value');
            }
        },

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
        }

    });

});

