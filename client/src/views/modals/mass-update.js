/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/modals/mass-update', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'mass-update',

        header: false,

        template: 'modals/mass-update',

        fullHeight: true,

        data: function () {
            return {
                scope: this.scope,
                fields: this.fields
            };
        },

        events: {
            'click button[data-action="update"]': function () {
                this.update();
            },
            'click .btn-select-field': function (e) {
                this.selectField();
            },
            'click .btn-select-attribute': function (e) {
                this.selectAttribute();
            }
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'update',
                    label: 'Update',
                    style: 'danger',
                    disabled: true
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                },
                {
                    name: 'selectField',
                    label: 'Select Field',
                    className: 'btn-select-field'
                }
            ];

            this.scope = this.options.scope;
            this.ids = this.options.ids;
            this.where = this.getWhere();
            this.selectData = this.options.selectData;
            this.byWhere = this.options.byWhere;

            this.header = this.translate(this.scope, 'scopeNamesPlural') + ' &raquo ' + this.translate('Mass Update');

            if (this.getMetadata().get(`scopes.${this.scope}.hasAttribute`)) {
                this.buttonList.push({
                    name: 'selectAttribute',
                    label: 'Select Attribute',
                    className: 'btn-select-attribute'
                });
            }

            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;
                this.model.set({ ids: this.ids });
                let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope) || [];

                this.fields = [];
                $.each((this.getMetadata().get(`entityDefs.${this.scope}.fields`) || {}), (field, row) => {
                    if (~forbiddenFieldList.indexOf(field)) return;
                    if (row.layoutMassUpdateDisabled) return;
                    if (row.massUpdateDisabled) return;
                    this.fields.push(field);
                });
                this.fields.sort()
            }.bind(this));

            this.fieldList = [];
        },

        selectField() {
            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'EntityField',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false,
                boolFilterList: [
                    "fieldsFilter",
                    "notLingual"
                ],
                boolFilterData: {
                    fieldsFilter: {
                        entityId: this.scope
                    }
                }
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', models => {
                    if (models.massRelate) {
                        models = dialog.collection.models;
                    }

                    this.notify('Loading...');
                    models.forEach(model => {
                        let name = model.get('code');
                        let type = model.get('type');
                        let label = this.translate(name, 'fields', this.scope);

                        if (['int', 'float', 'varchar'].includes(type) && this.model.getFieldParam(name, 'measureId')) {
                            label = this.translate('unit' + this.ucfirst(name), 'fields', this.scope);
                            this.model.defs['fields'][name]['view'] = `views/fields/unit-${type}`;
                        }

                        this.addField(name, label);

                        if (model.get('isMultilang')) {
                            $.each(this.getMetadata().get(`entityDefs.${this.scope}.fields`) || {}, (field, fieldDefs) => {
                                if (fieldDefs.multilangField === name) {
                                    this.addField(field, this.translate(field, 'fields', this.scope));
                                }
                            })
                        }
                    })
                    this.notify(false);
                });
            });
        },

        selectAttribute() {
            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'Attribute',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false,
                boolFilterData: {
                    onlyForEntity: this.scope
                },
                boolFilterList: ['onlyForEntity'],
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', models => {
                    if (models.massRelate) {
                        models = dialog.collection.models;
                    }

                    let attributesIds = [];
                    models.forEach(model => {
                        attributesIds.push(model.get('id'));
                    });
                    this.notify('Loading...');
                    let data = {
                        attributesIds: attributesIds,
                        entityName: this.model.name
                    };
                    this.ajaxGetRequest('Attribute/action/attributesDefs', data).success(res => {
                        $.each(res, (name, defs) => {
                            if (!defs.layoutDetailDisabled) {
                                this.model.defs['fields'][name] = defs;
                                this.addField(name, defs.label);
                            }
                        })
                    })
                    this.notify(false);
                });
            });
        },

        addField(name, label) {
            if (this.fieldList.includes(name)) {
                return;
            }
            let html = `<div class="cell form-group col-sm-6" data-name="${name}"><div class="pull-right inline-actions"></div><label class="control-label">${label}</label><div class="field" data-name="${name}" /></div>`;
            this.$el.find('.fields-container').append(html);

            let type = this.model.getFieldType(name);

            let viewName = this.model.getFieldParam(name, 'view') || this.model.getFieldParam(name, 'layoutDetailView') || this.getFieldManager().getViewName(type);

            this.createView(name, viewName, {
                model: this.model,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    isMassUpdate: true
                },
                mode: 'edit'
            }, view => {
                this.fieldList.push(name);
                view.render(() => {
                    this.initRemoveField(view);
                    this.enableButton('update');
                });
            });
        },

        initRemoveField(view) {
            const $cell = view.$el.parent();
            const $inlineActions = $cell.find('.inline-actions');
            $cell.find('.ph.ph-x').parent().remove();

            const $link = $(`<a href="javascript:" title="${this.translate('Cancel')}"><i class="ph ph-x"></i></a>`);

            $inlineActions.prepend($link);

            $link.on('click', () => {
                this.clearView(view);
                this.$el.find('.cell[data-name="' + view.name + '"]').remove();

                this.model.unset(view.name);

                let fieldList = [];
                this.fieldList.forEach(field => {
                    if (field !== view.name) {
                        fieldList.push(field);
                    }
                });
                this.fieldList = fieldList;

                if (this.fieldList.length === 0) {
                    this.disableButton('update');
                }
            });
        },

        actionUpdate: function () {
            let attributes = this.prepareData();
            this.model.set(attributes);
            if (!this.isValid()) {
                this.notify('Saving...');
                let toConfirm = false;
                $.each(attributes, (field, value) => {
                    if (value === '' || value === null) {
                        toConfirm = true;
                    }
                })

                if (toConfirm) {
                    this.confirm(this.translate('someFieldsEmptyOnMassUpdate', 'confirmations'), () => {
                        this.massUpdate(attributes);
                    });
                } else {
                    this.massUpdate(attributes);
                }
            } else {
                this.notify('Not valid', 'error');
                this.enableButton('update');
            }
        },

        ucfirst(val) {
            return String(val).charAt(0).toUpperCase() + String(val).slice(1);
        },

        massUpdate(attributes) {
            this.disableButton('update');
            let self = this;
            $.ajax({
                url: this.scope + '/action/massUpdate',
                type: 'PUT',
                data: JSON.stringify({
                    attributes: attributes,
                    ids: self.ids || null,
                    where: (!self.ids || self.ids.length == 0) ? self.options.where : null,
                    selectData: (!self.ids || self.ids.length == 0) ? self.options.selectData : null,
                    byWhere: this.byWhere
                }),
                success: function (result) {
                    self.trigger('after:update', result);
                },
                error: function () {
                    self.notify('Error occurred', 'error');
                    self.enableButton('update');
                }
            });
        },

        prepareData() {
            const attributes = {};
            const attributeIds = []

            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                _.extend(attributes, view.fetch());

                const attributeId = this.model.defs.fields[field]?.attributeId
                if (attributeId && !attributeIds.includes(attributeId)) {
                    attributeIds.push(attributeId)
                }
            }.bind(this));

            // attribute should be created if they do not exist
            if (attributeIds.length) {
                attributes.__attributes = attributeIds
            }
            return attributes;
        },

        isValid() {
            var notValid = false;
            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                notValid = view.validate() || notValid;
            }.bind(this));

            return notValid;
        },

        getWhere() {
            let where = this.options.where;
            let cleanWhere = (where) => {
                where.forEach(wherePart => {
                    if (['in', 'notIn'].includes(wherePart['type'])) {
                        if ('value' in wherePart && !(wherePart['value'] ?? []).length) {
                            delete wherePart['value']
                        }
                    }

                    if (['and', 'or'].includes(wherePart['type']) && Array.isArray(wherePart['value'] ?? [])) {
                        cleanWhere(wherePart['value'] ?? [])
                    }
                })
            };
            cleanWhere(where);
            return where;
        }
    });
});
