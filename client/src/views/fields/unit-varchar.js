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

Espo.define('views/fields/unit-varchar', 'views/fields/varchar', Dep => {

    return Dep.extend({

        listLinkTemplate: 'fields/varchar/list-link',

        setup() {
            Dep.prototype.setup.call(this);
            this.prepareOriginalName()
            this.afterSetup();
        },

        setMode(mode) {
            this.setTemplateFromMeasureFormat(mode)
            Dep.prototype.setMode.call(this, mode)
        },

        getAttributeList() {
            return [this.unitFieldName].concat(Dep.prototype.getAttributeList.call(this))
        },


        afterSetup() {
            if (this.measureId) {
                this.unitFieldName = this.originalName + 'UnitId';
                if (this.model.isNew() && this.defaultUnit) {
                    this.model.set(this.unitFieldName, this.defaultUnit);
                }

                this.events = _.extend({
                    [`change [name="${this.unitFieldName}"]`]: function (e) {
                        this.model.set({[this.unitFieldName]: $(e.target).val()}, {ui: true})
                    },
                }, this.events || {});
            }
        },

        init() {
            this.prepareOptionName();
            Dep.prototype.init.call(this);
        },

        prepareOptionName() {
            let fieldName = this.options.name || this.options.defs.name;
            this.options.name = this.getMetadata().get(['entityDefs', this.model.name, 'fields', fieldName, 'mainField']) || fieldName;
        },

        isInheritedField: function () {
            if (!['detail', 'edit'].includes(this.mode) || !this.model || !this.model.urlRoot || !this.isInheritableField()) {
                return false;
            }

            const inheritedFields = this.model.get('inheritedFields');

            return inheritedFields && Array.isArray(inheritedFields) && inheritedFields.includes(this.originalName) && inheritedFields.includes(this.originalName + 'Unit');
        },
        setDataWithOriginalName() {
            const data = Dep.prototype.data.call(this);
            data.value = this.model.get(this.originalName)

            if (
                this.model.get(this.originalName) !== null
                &&
                this.model.get(this.originalName) !== ''
                &&
                this.model.has(this.originalName)
            ) {
                data.isNotEmpty = true;
            }
            data.valueIsSet = this.model.has(this.originalName);
            return data;
        },
        data() {
            return this.prepareMeasureData(this.setDataWithOriginalName());
        },
        prepareOriginalName() {
            this.originalName = this.name;
            if (this.measureId) {
                this.name = "unit" + this.originalName.charAt(0).toUpperCase() + this.originalName.slice(1)
            }
        },

        prepareMeasureData(data) {
            if (this.measureId) {
                data.unitFieldName = this.unitFieldName;
                data.unitValue = this.model.get(this.unitFieldName);
                const unitData = this.model.get(this.originalName + 'UnitData')
                if (this.mode === 'edit' || !unitData) {
                    this.loadUnitOptions();
                    data.unitList = this.unitList;
                    data.unitListTranslates = this.unitListTranslates;
                    data.unitValueTranslate = this.unitListTranslates[data.unitValue] || data.unitValue;
                    data.unitSymbol = this.unitListSymbols[data.unitValue]
                } else {
                    data.unitValueTranslate = unitData['name'] || data.unitValue;
                    data.unitSymbol = unitData['symbol']
                }
            }
            return data;
        },

        setTemplateFromMeasureFormat(mode) {
            const templates = {
                detailTemplate1: 'fields/varchar/detail-1',
                detailTemplate2: 'fields/varchar/detail-2',
                listTemplate1: 'fields/varchar/list-1',
                listTemplate2: 'fields/varchar/list-2',
                listLinkTemplate1: 'fields/varchar/list-link-1',
                listLinkTemplate2: 'fields/varchar/list-link-2'
            }

            if (['detail', 'list', 'listLink'].includes(mode)) {
                const data = this.model.get(this.options.name + 'UnitData')
                const format = (data ? data.displayFormat : this.getMeasureFormat()) ?? '';

                let prop = this.mode + 'Template' + format;
                if (prop in templates) {
                    if (this.mode === 'list') {
                        this.listTemplate = templates[prop]
                    } else if (this.mode === 'listLink') {
                        this.listLinkTemplate = templates[prop]
                    } else {
                        this.detailTemplate = templates[prop]
                    }
                }
            }
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (!this.model.get(this.originalName)) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }

            return false;
        },

        addMeasureDataOnFetch(data) {
            let $unit = this.$el.find(`[name="${this.unitFieldName}"]`);
            data[this.unitFieldName] = $unit ? $unit.val() : null;
            data[this.originalName] = data[this.name]
            delete data[this.name];
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            this.addMeasureDataOnFetch(data)
            return data;
        }

    });
});
