/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/default', 'views/fields/base', Dep => {

    return Dep.extend({

        listTemplate: 'fields/field-value-container',

        detailTemplate: 'fields/field-value-container',

        editTemplate: 'fields/field-value-container',

        setup() {
            Dep.prototype.setup.call(this);

            this.name = this.options.name || this.defs.name;

            this.listenTo(this.model, 'change:type', () => {
                if (this.mode === 'edit') {
                    this.model.set('value', null);
                    this.reRender();
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let type = this.model.get('type');

            if (type) {
                const types = {
                    link: "views/admin/field-manager/fields/link-default",
                    measure: "views/admin/field-manager/fields/measure-default",
                    file: "views/admin/field-manager/fields/file-default",
                    extensibleEnum: "views/admin/field-manager/fields/extensible-enum-default",
                    linkMultiple: "views/admin/field-manager/fields/link-multiple-default",
                    extensibleMultiEnum: "views/admin/field-manager/fields/extensible-multi-enum-default",
                }

                const fieldView = types[type] ?? this.getFieldManager().getViewName(type);

                let params = {
                    required: false,
                    readOnly: false
                };

                let options = {
                    el: `${this.options.el} > .field[data-name="valueField"]`,
                    name: this.name,
                    model: this.model,
                    collection: this.model.collection || null,
                    params: params,
                    mode: this.mode,
                    inlineEditDisabled: true
                };

                this.createView('valueField', fieldView, options, view => {
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

    });

});

