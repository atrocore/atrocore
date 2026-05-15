/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/conditions-container', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'fields/field-value-container',
        detailTemplate: 'fields/field-value-container',
        editTemplate: 'fields/field-value-container',

        inlineEditDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.entityTypeField = this.model.name === 'Action' ? 'sourceEntity' : 'entityType';

            this.wait(true);
            this.onModelReady(() => {
                this.listenTo(this.model, `change:${this.entityTypeField}`, () => {
                    this.model.set(this.name, null);
                    this.reRender();
                });
                this.wait(false);
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const entityType = this.model.get(this.entityTypeField);
            if (!entityType) {
                return;
            }

            this.createView('valueField', 'views/admin/field-manager/fields/dynamic-logic-conditions', {
                el: `${this.options.el} > .field[data-name="valueField"]`,
                name: this.name,
                model: this.model,
                scope: entityType,
                params: {required: false},
                inlineEditDisabled: true,
                mode: this.mode,
            }, view => view.render());
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

        reRenderByConditionalProperties() {
            this.toggleReadOnlyViaConditions();
            if (this.getConditions('visible')) {
                this.toggleVisibility();
            }
            if (this.getConditions('required')) {
                this.toggleRequiredMarker();
            }
        },

    })
);
