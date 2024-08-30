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

Espo.define('views/admin/layouts/detail', 'views/admin/layouts/grid', function (Dep) {

    return Dep.extend({

        layoutDisabledParameter: 'layoutDetailDisabled',

        dataAttributeList: ['id', 'name', 'fullWidth', 'customLabel', 'noLabel'],

        panelDataAttributeList: ['id', 'panelName', 'style', 'dynamicLogicVisible'],

        dataAttributesDefs: {
            fullWidth: {
                type: 'bool'
            },
            name: {
                readOnly: true
            },
            customLabel: {
                type: 'varchar',
                readOnly: true
            },
            noLabel: {
                type: 'bool',
                readOnly: true
            }
        },

        panelDataAttributesDefs: {
            panelName: {
                type: 'varchar',
            },
            style: {
                type: 'enum',
                options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
                translation: 'LayoutManager.options.style'
            },
            dynamicLogicVisible: {
                type: 'base',
                view: 'views/admin/field-manager/fields/dynamic-logic-conditions'
            }
        },

        defaultPanelFieldList: ['modifiedAt', 'createdAt', 'modifiedBy', 'createdBy', 'assignedUser', 'ownerUser', 'teams'],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.panelDataAttributesDefs = Espo.Utils.cloneDeep(this.panelDataAttributesDefs);
            this.panelDataAttributesDefs.dynamicLogicVisible.scope = this.scope;

            this.defaultPanelFieldList.forEach(item => {
                if (this.getConfig().get('isMultilangActive')) {
                    let isMultilang = this.getMetadata().get(['entityDefs', this.scope, 'fields', item, 'isMultilang']) || null;

                    if (isMultilang) {
                        (this.getConfig().get('inputLanguageList') || []).forEach(curr => {
                            let field = curr.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), item);

                            this.defaultPanelFieldList.push(field);
                        });
                    }
                }
            });

            this.wait(true);
            this.loadLayout(function () {

                this.setupPanels();
                this.wait(false);
            }.bind(this));
        },

        loadLayout: function (callback) {
            var layout;
            var model;

            var promiseList = [];

            promiseList.push(
                new Promise(function (resolve) {
                    this.getModelFactory().create(this.scope, function (m) {
                        this.getHelper().layoutManager.get(this.scope, this.type, function (layoutLoaded) {
                            layout = layoutLoaded;
                            model = m;
                            resolve();
                        });
                    }.bind(this));
                }.bind(this))
            );

            if (~['detail', 'detailSmall'].indexOf(this.type)) {
                promiseList.push(
                    new Promise(function (resolve) {
                        this.getHelper().layoutManager.get(this.scope, 'sidePanels' + Espo.Utils.upperCaseFirst(this.type), function (layoutLoaded) {
                            this.sidePanelsLayout = layoutLoaded;
                            resolve();
                        }.bind(this));
                    }.bind(this))
                );
            }

            Promise.all(promiseList).then(function () {
                this.readDataFromLayout(model, layout);
                if (callback) {
                    callback();
                }
            }.bind(this));

        },

        readDataFromLayout: function (model, layout) {
            var allFields = [];
            const labels = [];
            for (var field in model.defs.fields) {
                if (this.isFieldEnabled(model, field)) {
                    labels.push(this.getLanguage().translate(field, 'fields', this.scope));
                    allFields.push(field);
                }
            }

            const duplicatedLabels = labels.filter((label, index) => labels.indexOf(label) !== index);
            this.enabledFields = [];
            this.disabledFields = [];

            this.panels = layout;

            layout.forEach(function (panel, panelNum) {
                panel.rows.forEach(function (row, rowNum) {
                    if (row) {
                        row.forEach(function (cell, i) {
                            if (i == this.columnCount) {
                                return;
                            }
                            let label = this.getLanguage().translate(cell.name, 'fields', this.scope);
                            if (~duplicatedLabels.indexOf(label)) {
                                label += ' (' + cell.name + ')';
                            }
                            this.enabledFields.push({
                                name: cell.name,
                                label: label
                            });
                            this.panels[panelNum].rows[rowNum][i].label = label;
                        }.bind(this));
                    }
                }.bind(this));
            }.bind(this));

            allFields.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));


            for (var i in allFields) {
                if (!this.hasField(allFields[i], this.enabledFields)) {
                    const field = allFields[i];
                    let label = this.getLanguage().translate(field, 'fields', this.scope);
                    if (~duplicatedLabels.indexOf(label)) {
                        label += ' (' + field + ')';
                    }
                    this.disabledFields.push({
                        name: field,
                        label: label
                    });
                }
            }
        },

        hasField: function (name, list) {
            return list.filter(field => field.name == name).length > 0;
        },

        isFieldEnabled: function (model, name) {
            if (this.hasDefaultPanel()) {
                if (this.defaultPanelFieldList.indexOf(name) !== -1) {
                    return false;
                }
            }
            return !model.getFieldParam(name, 'disabled') && !model.getFieldParam(name, this.layoutDisabledParameter);
        },

        hasDefaultPanel: function () {
            if (this.getMetadata().get(['clientDefs', this.scope, 'defaultSidePanel', this.viewType]) === false) return false;
            if (this.getMetadata().get(['clientDefs', this.scope, 'defaultSidePanelDisabled'])) return false;

            if (this.sidePanelsLayout) {
                for (var name in this.sidePanelsLayout) {
                    if (name === 'default' && this.sidePanelsLayout[name].disabled) {
                        return false;
                    }
                }
            }

            return true;
        }
    });
});
