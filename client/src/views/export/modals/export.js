

Espo.define('views/export/modals/export', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        cssName: 'export-modal',

        template: 'export/modals/export',

        data: function () {
            return {
            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'export',
                    label: 'Export',
                    style: 'danger'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.model = new Model();
            this.model.name = 'Export';

            this.scope = this.options.scope;

            if (this.options.fieldList) {
                this.model.set('fieldList', this.options.fieldList);
                this.model.set('exportAllFields', false);
            } else {
                this.model.set('exportAllFields', true);
            }
            this.model.set('format', this.getMetadata().get('app.export.formatList')[0]);

            this.createView('record', 'views/export/record/record', {
                scope: this.scope,
                model: this.model,
                el: this.getSelector() + ' .record'
            });
        },

        actionExport: function () {
            var data = this.getView('record').fetch();
            this.model.set(data);
            if (this.getView('record').validate()) return;

            var returnData = {
                exportAllFields: data.exportAllFields,
                format: data.format
            };

            if (!data.exportAllFields) {
                var attributeList = [];
                data.fieldList.forEach(function (item) {
                    if (item === 'id') {
                        attributeList.push('id');
                        return;
                    }
                    var type = this.getMetadata().get(['entityDefs', this.scope, 'fields', item, 'type']);
                    if (type) {;
                        this.getFieldManager().getAttributeList(type, item).forEach(function (attribute) {
                            attributeList.push(attribute);
                        }, this);
                    }
                    if (~item.indexOf('_')) {
                        attributeList.push(item);
                    }
                }, this);
                returnData.attributeList = attributeList;
                returnData.fieldList = data.fieldList;
            }

            this.trigger('proceed', returnData);
            this.close();
        }

    });
});

