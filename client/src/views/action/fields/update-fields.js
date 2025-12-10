/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/update-fields', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'fields/field-value-container',
        detailTemplate: 'fields/field-value-container',
        editTemplate: 'fields/field-value-container',

        inlineEditDisabled: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.onModelReady(() => {
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

                this.createView('valueField', 'views/action/record/panels/fields', options, view => {
                    view.render();
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let view = this.getView('valueField');
            if (view) {
                view.reRender();
            }
        },

        fetch() {
            let data = {};
            let view = this.getView('valueField');
            if (view) {
                _.extend(data, view.fetchData());
            }

            return data;
        },

        validate() {
            if (this.model.get('updateType') !== 'basic') {
                return false;
            }

            let view = this.getView('valueField');
            if (view && JSON.stringify(view.fetchData()) === '{}') {
                this.$el.parents('.cell').addClass('has-error');
                this.$el.css('border-bottom', '1px solid #a94442');

                return true;
            }

            return false;
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
