/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/conditions-container', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'fields/field-value-container',
        detailTemplate: 'fields/field-value-container',
        editTemplate: 'fields/field-value-container',

        className: 'conditions-container',

        inlineEditDisabled: true,
        entityTypeField: 'entityType',

        twigVariables: [
            "entity"
        ],

        data: function () {
            const data = Dep.prototype.data.call(this) || {};

            data.className = this.className;

            return data;
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.wait(true)
            this.onModelReady((onSync) => {
                this.listenTo(this.model, `change:${this.entityTypeField} change:conditionsType`, () => {
                    this.clearValue();
                    this.reRender();
                });

                this.wait(false)
            })

            this.listenTo(this.model, `apply:${this.name}`, () => {
                this.trigger('change');
            })
        },

        clearValue() {
            if (!this.getView('valueField')) {
                // try later
                setTimeout(() => this.clearValue(), 100);
            } else {
                if (this.getConditionType() === 'script') {
                    this.model.set(this.name, "{% set proceed = true %}" + "\n{{ proceed }}");
                } else {
                    this.model.set(this.name, null);
                }
            }
        },

        getEntityType() {
            return this.model.get(this.entityTypeField);
        },

        getConditionType(){
            return this.model.get('conditionsType') || 'basic'
        },

        canShowValueField() {
            return this.getConditionType() && this.getEntityType()
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.hide();
            if (this.canShowValueField()) {
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
                if (this.getConditionType() === 'basic') {
                    view = 'views/admin/field-manager/fields/dynamic-logic-conditions';
                    options.scope = this.getEntityType();
                } else if (this.getConditionType() === 'script') {
                    view = 'views/fields/script'
                    options.params.required = true;
                    options.params.twigVariables = this.twigVariables
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
