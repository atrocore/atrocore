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
                $.each(this.model.defs.fields || {}, (name, defs) => {
                    if (defs.attributeId === attributeId && !defs.layoutDetailDisabled) {
                        defs['disableAttributeRemove'] = true;
                        this.childrenFields.push({
                            name: name,
                            label: defs.detailViewLabel || defs.label,
                            fullWidth: !!defs.fullWidth,
                            params: defs
                        });
                    }
                });
            })

            this.childrenFields.forEach(child => {
                let view = this.getFieldManager().getViewName(child.params.type);
                if (child.params.layoutDetailView) {
                    view = child.params.layoutDetailView;
                }

                if ((this.getAcl().getScopeForbiddenFieldList(this.model.name, 'edit') || []).includes(child.name)){
                    this.model.defs.fields[child.name].readOnly = true;
                }

                this.createView(child.name, view, {
                    el: `${this.options.el} > .composite-container .field[data-name="${child.name}"]`,
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
                childrenFields: this.childrenFields,
                childrenRows: this.getChildrenRows()
            };
        },

        initInlineEdit() {
        },

        getChildrenRows: function () {
            const rows = [];
            let currentRow = [];

            const addToRows = (child) => {
                if (child.fullWidth) {
                    if (currentRow.length) {
                        rows.push(currentRow);
                        currentRow = [];
                    }
                    rows.push([child]);
                    return;
                }

                if (currentRow.length === 2) {
                    rows.push(currentRow);
                    currentRow = [];
                }
                currentRow.push(child);
            };

            this.childrenFields.forEach(addToRows);

            if (currentRow.length) {
                rows.push(currentRow);
            }

            return rows;
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

            return data;
        },

        validate() {
            let validate = false;

            this.childrenFields.forEach(child => {
                if (this.getView(child.name).validate()) {
                    validate = true;
                }
            });

            return validate;
        },

    })
);

