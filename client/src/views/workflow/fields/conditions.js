/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/workflow/fields/conditions', ['views/fields/base', 'lib!Twig'],
    Dep => Dep.extend({

        listTemplate: 'fields/field-value-container',
        detailTemplate: 'fields/field-value-container',
        editTemplate: 'fields/field-value-container',

        inlineEditDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityType change:conditionsType', () => {
                this.clearValue();
                this.reRender();
            });
        },

        clearValue() {
            if (this.model.get('conditionsType') === 'script') {
                this.model.set('conditions', "{% set proceed = true %}" + "\n{{ proceed }}");
            } else {
                this.model.set('conditions', null);
            }
        },

        afterRender() {
            Dep.prototype.setup.call(this);

            this.hide();
            if (this.model.get('conditionsType') && this.model.get('entityType')) {
                let options = {
                    el: `${this.options.el} > .field[data-name="valueField"]`,
                    name: this.name,
                    model: this.model,
                    params: {
                        required: false
                    },
                    inlineEditDisabled: true,
                    mode: this.mode
                };

                let view;
                if (this.model.get('conditionsType') === 'basic') {
                    view = 'views/admin/field-manager/fields/dynamic-logic-conditions';
                    options.scope = this.model.get('entityType');
                } else if (this.model.get('conditionsType') === 'script') {
                    view = 'views/fields/script'
                    options.params.required = true;
                    options.params.useDisabledTextareaInViewMode = true;
                }

                this.show();
                this.createView('valueField', view, options, view => {
                    view.render();
                });
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

    })
);
