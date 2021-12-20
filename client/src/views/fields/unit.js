/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

Espo.define('views/fields/unit', 'views/fields/float',
    Dep => Dep.extend({

        type: 'unit',

        editTemplate: 'fields/unit/edit',

        detailTemplate: 'fields/unit/detail',

        listTemplate: 'fields/unit/list',

        prohibitedEmptyValue: false,

        localedOptions: true,

        data() {
            let data = Dep.prototype.data.call(this);

            data.unitFieldName = this.unitFieldName;
            data.unitList = this.unitList;
            data.unitListTranslates = this.unitListTranslates;
            data.unitValue = this.model.get(this.unitFieldName);
            data.unitValueTranslate = this.unitListTranslates[data.unitValue] || data.unitValue;

            return data;
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.unitFieldName = this.name + 'Unit';
            this.prohibitedEmptyValue = this.prohibitedEmptyValue || this.options.prohibitedEmptyValue || this.model.getFieldParam(this.name, 'prohibitedEmptyValue');

            this.loadUnitList();
            if (this.localedOptions) {
                this.prepareDefault();
            }
        },

        prepareDefault() {
            if (this.mode === 'edit' && !this.model.get('id')) {
                let defaultValue = this.model.getFieldParam(this.name, 'default');
                let defaultUnit = this.model.getFieldParam(this.name, 'defaultUnit');
                if (!this.unitList.includes(defaultUnit)) {
                    let defaultUnitData = this.getUnitData(defaultUnit)
                    if (defaultUnitData) {
                        let convertTo = null;

                        if (defaultUnitData.convertToId) {
                            convertTo = this.getConfig().get('unitsOfMeasure')[this.params.measure].unitListData[defaultUnitData.convertToId];
                            if (!this.unitList.includes(convertTo.name)) {
                                convertTo = null;
                            }
                        }

                        if (convertTo === null) {
                            convertTo = this.getConfig().get('unitsOfMeasure')[this.params.measure].unitListData[this.getLocaledDefaultUnitId()];
                        }

                        this.model.set(this.name, this.convertValue(defaultValue, defaultUnitData.id, convertTo.id));
                        this.model.set(this.unitFieldName, convertTo.name);
                    }
                }
            }
        },

        loadUnitList() {
            this.unitList = [];
            this.unitListTranslates = {};

            if (!this.prohibitedEmptyValue) {
                this.unitList.push('');
                this.unitListTranslates[''] = '';
            }

            if (this.params.measure) {
                const unitsOfMeasure = this.getConfig().get('unitsOfMeasure') || {};
                const measureConfig = unitsOfMeasure[this.params.measure] || {};

                if (measureConfig.unitList) {
                    if (this.localedOptions) {
                        this.unitList = this.getLocaledUnitList(measureConfig);
                    } else {
                        this.unitList = measureConfig.unitList;
                    }

                    let unitListTranslates = [];
                    if (measureConfig.unitListTranslates && measureConfig.unitListTranslates[this.getLanguage().name]) {
                        unitListTranslates = measureConfig.unitListTranslates[this.getLanguage().name];
                    }

                    measureConfig.unitList.forEach((unitName, k) => {
                        if (unitListTranslates[k] && this.unitList.includes(unitName)) {
                            this.unitListTranslates[unitName] = unitListTranslates[k];
                        }
                    });
                }
            }
        },

        formatNumber(value) {
            if (this.mode === 'list' || this.mode === 'detail') {
                return this.formatNumberDetail(value);
            }
            return this.formatNumberEdit(value);
        },

        formatNumberEdit(value) {
            if (value !== null) {
                let parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);

                return parts.join(this.decimalMark);
            }
            return '';
        },

        formatNumberDetail(value) {
            if (value !== null) {
                let parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);

                return parts.join(this.decimalMark);
            }
            return '';
        },

        validateFloat: function () {
            if (Dep.prototype.validateFloat.call(this)) {
                return true;
            }

            return this.model.get(this.name) && !this.$unit.val();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                if (!this.model.get(this.name) && this.model.getFieldParam(this.name, 'default')) {
                    this.model.set(this.name, this.model.getFieldParam(this.name, 'default'));
                }

                if (!this.model.get(this.unitFieldName) && this.model.getFieldParam(this.name, 'defaultUnit')) {
                    this.model.set(this.unitFieldName, this.model.getFieldParam(this.name, 'defaultUnit'));
                }
                this.$unit = this.$el.find(`[name="${this.unitFieldName}"]`);
                this.$unit.on('change', () => this.model.set(this.unitFieldName, this.$unit.val()));
            }
        },

        getLocaledUnitsIds() {
            let localeId = this.getPreferences().get('locale') || this.getConfig().get('localeId');
            let localeMeasures = this.getConfig().get('locales')[localeId]['measures'] || [];

            let ids = [];
            localeMeasures.forEach(localeMeasure => {
                if (localeMeasure.name === this.params.measure) {
                    ids = localeMeasure.units;
                }
            });

            return ids;
        },

        getLocaledDefaultUnitId() {
            let localeId = this.getPreferences().get('locale') || this.getConfig().get('localeId');
            let localeMeasures = this.getConfig().get('locales')[localeId]['measures'] || [];

            let id = null;
            localeMeasures.forEach(localeMeasure => {
                if (localeMeasure.name === this.params.measure) {
                    id = localeMeasure.defaultUnit;
                }
            });

            return id;
        },

        getLocaledUnitList(unitsOfMeasure) {
            let result = [];
            this.getLocaledUnitsIds().forEach(id => {
                result.push(unitsOfMeasure.unitListData[id].name);
            });

            if (result.length === 0) {
                result = measureConfig.unitList;
            }

            return result;
        },

        getUnitData(unitName) {
            let result = null;

            if (this.getConfig().get('unitsOfMeasure') && this.getConfig().get('unitsOfMeasure')[this.params.measure]) {
                $.each(this.getConfig().get('unitsOfMeasure')[this.params.measure].unitListData, (id, row) => {
                    if (unitName === row.name) {
                        result = row;
                    }
                });
            }

            return result;
        },

        convertValue(value, from, to) {
            value = value / this.getConfig().get('unitsOfMeasure')[this.params.measure].unitListData[from].multiplier;
            value = value * this.getConfig().get('unitsOfMeasure')[this.params.measure].unitListData[to].multiplier;

            return parseFloat(value.toFixed(4));
        },

        fetch: function () {
            let data = {};

            let value = this.$element.val();
            value = this.parse(value);

            data[this.name] = value;
            data[this.unitFieldName] = this.$unit.val();

            return data;
        },
    })
);