/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/composite', 'views/fields/base', Dep => Dep.extend({

        detailTemplate: 'fields/composite/detail',

        editTemplate: 'fields/composite/edit',

        childrenFields: [],

        setup() {
            this.name = this.options.name || this.defs.name;

            this.childrenFields = [];
            (this.model.getFieldParam(this.name, 'childrenIds') || []).forEach(attributeId => {
                $.each(this.model.defs.fields || {}, (name, params) => {
                    if (params.attributeId === attributeId) {
                        this.childrenFields.push({
                            name: name,
                            label: params.detailViewLabel || params.label,
                            params: params
                        });
                    }
                });
            })

            this.childrenFields.forEach(child => {
                this.createView(child.name, this.getFieldManager().getViewName(child.params.type), {
                    el: `${this.options.el} > .composite-container > .composite-container-field[data-name="${child.name}"]`,
                    name: child.name,
                    model: this.model,
                    params: child.params,
                    mode: this.mode
                }, view => {
                    view.render();
                })
            })
        },

        data() {
            return {
                childrenFields: this.childrenFields
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
        },

        initInlineEdit() {
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            this.childrenFields.forEach(child => {
                this.getView(child.name).setMode(mode);
            });
        },

        fetch() {
            let data = {};

            this.childrenFields.forEach(child => {
                _.extend(data, this.getView(child.name).fetch());
            });

            console.log(data)

            return data;
        },

        // validate() {
        //     let validate = false;
        //     let view = this.getView('valueField');
        //     if (view) {
        //         validate = view.validate();
        //     }
        //     return validate;
        // },

    })
);

