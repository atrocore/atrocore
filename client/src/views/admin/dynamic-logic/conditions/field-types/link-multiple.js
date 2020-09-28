

Espo.define('views/admin/dynamic-logic/conditions/field-types/link-multiple', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        getValueFieldName: function () {
            return this.name;
        },

        getValueViewName: function () {
            return 'views/fields/link';
        },

        createValueViewContains: function () {
            this.createLinkValueField();
        },

        createValueViewNotContains: function () {
            this.createLinkValueField();
        },

        createLinkValueField: function () {
            var viewName = 'views/fields/link'
            var fieldName = 'link';
            this.createView('value', viewName, {
                model: this.model,
                name: fieldName,
                el: this.getSelector() + ' .value-container',
                mode: 'edit',
                readOnlyDisabled: true,
                foreignScope: this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'entity']) || this.getMetadata().get(['entityDefs', this.scope, 'links', this.field, 'entity'])
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field + 'Ids',
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get('linkId');

                var values = {};
                values['linkName'] = this.model.get('linkName');
                values['linkId'] = this.model.get('linkId');
                item.data.values = values;
            }

            return item;
        }

    });

});

