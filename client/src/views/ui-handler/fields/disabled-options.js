/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/ui-handler/fields/disabled-options', 'views/fields/base', Dep => {

    return Dep.extend({
        listTemplate: 'fields/field-value-container',

        detailTemplate: 'fields/field-value-container',

        editTemplate: 'fields/field-value-container',

        setup() {
            this.name = this.options.name || this.defs.name;
            this.listenTo(this.model, 'change:fields', () => {
                if (this.mode === 'edit') {
                    this.model.set('disabledOptions', []);
                    this.reRender();
                }
            });

            this.listenTo(this.model, 'change:type change:entityType', () => {
                this.reRender();
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let field = null
            if (this.model.get('fields') && this.model.get('fields')[0]) {
                field = this.model.get('fields')[0]
            }
            const scope = this.model.get('entityType')

            if (field && this.model.get('type') === 'ui_disable_options') {
                const defs = this.getMetadata().get(['entityDefs', scope, 'fields', field]);

                if (defs && ['enum', 'multiEnum', 'groupedEnum', 'extensibleEnum', 'extensibleMultiEnum'].includes(defs['type'])) {
                    const type = ['enum', 'multiEnum', 'groupedEnum'].includes(defs['type']) ? 'multiEnum' : 'extensibleMultiEnum'
                    let fieldView = this.getFieldManager().getViewName(type);

                    let params = {
                        required: false,
                        readOnly: false
                    };

                    if (type === "multiEnum") {
                        if (defs['type'] === 'groupedEnum') {
                            const groups = this.getMetadata().get(['entityDefs', scope, 'fields', field, 'groups']) || {};
                            params.options = [];

                            Object.keys(groups).forEach(group => {
                                params.options.push(...groups[group])
                            })
                        } else {
                            params.optionsIds = this.getMetadata().get(['entityDefs', scope, 'fields', field, 'optionsIds']) || [];
                            params.options = this.getMetadata().get(['entityDefs', scope, 'fields', field, 'options']) || []
                        }
                    } else {
                        params.extensibleEnumId = this.getMetadata().get(['entityDefs', scope, 'fields', field, 'extensibleEnumId'])
                    }

                    let options = {
                        el: `${this.options.el} > .field[data-name="valueField"]`,
                        name: this.name,
                        model: this.model,
                        collection: this.model.collection || null,
                        params: params,
                        mode: this.mode,
                        inlineEditDisabled: true
                    };
                    if (type === 'extensibleMultiEnum') {
                        options.createDisabled = true
                    }

                    this.createView('valueField', fieldView, options, view => {
                        view.render();
                    });
                }
            }

            if (this.mode !== 'list') {
                if (this.model.get('type') === 'ui_disable_options' && field) {
                    this.$el.parent().show();
                } else {
                    this.$el.parent().hide();
                }
            }
        },

        fetch() {
            let data = {};
            let view = this.getView('valueField');
            if (view) {
                _.extend(data, view.fetch());
            }

            return data;
        },

        validate() {
            let validate = false;
            let view = this.getView('valueField');
            if (view) {
                validate = view.validate();
            }
            return validate;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            let valueField = this.getView('valueField');
            if (valueField) {
                valueField.setMode(mode);
            }
        },

    });
});

