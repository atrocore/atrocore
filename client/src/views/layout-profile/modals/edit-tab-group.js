/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/edit-tab-group', 'views/modal',
    Dep => Dep.extend({

        template: 'layout-profile/modals/edit-tab-group',

        buttonList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup() {
            this.header = this.translate(this.options.attributes ? 'editGroup' : 'addGroup');

            this.getModelFactory().create(null, model => {
                if (this.options.attributes) {
                    model.set(this.options.attributes);
                }
                this.model = model;
                this.attributes = this.model.getClonedAttributes();
                this.buildFields();
            });
        },

        buildFields() {
            this.createView('name', 'views/fields/varchar', {
                model: this.model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="name"]',
                defs: {
                    name: 'name',
                    params: {
                        required: true
                    }
                }
            });

            if (!this.getConfig().get('scopeColorsDisabled')) {
                this.createView('color', 'views/fields/colorpicker', {
                    model: this.model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="color"]',
                    defs: {
                        name: 'color'
                    }
                });
            }

            this.createView('iconClass', 'views/admin/entity-manager/fields/icon-class', {
                model: this.model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="iconClass"]',
                defs: {
                    name: 'iconClass'
                }
            });
        },

        fetch() {
            if (this.getView('name')) {
                this.getView('name').fetchToModel();
            }
            if (this.getView('color')) {
                this.getView('color').fetchToModel();
            }
            if (this.getView('iconClass')) {
                this.getView('iconClass').fetchToModel();
            }

            return {
                name: this.model.get('name') || '',
                color: this.model.get('color') || '',
                iconClass: this.model.get('iconClass') || ''
            }
        },

        actionSave() {
            let data = this.fetch();

            if (this.validate()) {
                this.trigger('cancel:save');
                this.notify('Not valid', 'error');
                this.enableButtons();
                return;
            }

            this.trigger('after:save', data);
            this.close();
        },

        validate() {
            let notValid = false;
            let fields = this.getFieldViews();
            for (let i in fields) {
                if (fields[i].mode === 'edit') {
                    if (!fields[i].disabled && !fields[i].readOnly) {
                        notValid = fields[i].validate() || notValid;
                    }
                }
            }
            return notValid
        },

        getFieldViews: function () {
            let fields = {};
            Object.keys(this.nestedViews || {}).forEach(function (item) {
                let view = this.getView(item);
                if (view) {
                    fields[item] = view;
                }
            }, this);
            return fields;
        },

        enableButtons() {
            this.$el.find(".button-container button").removeAttr('disabled');
        },

        disableButtons() {
            this.$el.find(".button-container button").attr('disabled', 'disabled');
        },

    })
);