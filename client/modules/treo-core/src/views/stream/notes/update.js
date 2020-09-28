

Espo.define('treo-core:views/stream/notes/update', 'views/stream/notes/update', function (Dep) {

    return Dep.extend({

        template: 'treo-core:stream/notes/update',

        customLabels: {},

        setup: function () {
            var data = this.model.get('data');

            var fields = data.fields || [];

            this.createMessage();

            this.wait(true);
            this.getModelFactory().create(this.model.get('parentType'), function (model) {
                var modelWas = model;
                var modelBecame = model.clone();

                data.attributes = data.attributes || {};

                modelWas.set(data.attributes.was);
                modelBecame.set(data.attributes.became);

                this.fieldsArr = [];

                fields.forEach(function (field) {
                    if (model.getFieldParam(field, 'isMultilang') && !modelWas.has(field) && !modelBecame.has(field)) {
                        return;
                    }
                    let type = this.model.get('attributeType') || model.getFieldType(field) || 'base';
                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);
                    this.createView(field + 'Was', viewName, {
                        el: this.options.el + '.was',
                        model: modelWas,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true
                    });
                    this.createView(field + 'Became', viewName, {
                        el: this.options.el + '.became',
                        model: modelBecame,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true
                    });

                    this.fieldsArr.push({
                        field: field,
                        was: field + 'Was',
                        became: field + 'Became',
                        customLabel: this.customLabels[field] ? this.customLabels[field] : false
                    });

                }, this);

                this.wait(false);

            }, this);
        },

        getInputLangName(lang, field) {
            return lang.split('_').reduce((prev, curr) => prev + Espo.utils.upperCaseFirst(curr.toLowerCase()), field);
        },

        getCustomLabel(field, langField) {
            let label = '';
            label += this.translate(field, 'fields', this.model.get('parentType')) + ' &#8250; ';
            label += langField.slice(-4, -2).toLowerCase() + "_" + langField.slice(-2).toUpperCase();
            return label;
        }
    });
});

