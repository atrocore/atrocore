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

Espo.define('views/fields/unit-float', ['views/fields/float', 'views/fields/unit-varchar'], (Dep, Varchar) => {

    return Dep.extend({
        listLinkTemplate: 'fields/varchar/list-link',

        setup() {
            Dep.prototype.setup.call(this);

            Varchar.prototype.prepareOriginalName.call(this);

            Varchar.prototype.afterSetup.call(this);
        },

        onInlineEditSave(res, attrs, model){
            Varchar.prototype.onInlineEditSave.call(this, res, attrs, model);
        },

        setMode(mode) {
            Varchar.prototype.setTemplateFromMeasureFormat.call(this,mode);
            Dep.prototype.setMode.call(this, mode)
        },

        init() {
            Varchar.prototype.prepareOptionName.call(this);
            Dep.prototype.init.call(this);
        },

        isInheritedField: function () {
            return Varchar.prototype.isInheritedField.call(this);
        },

        data() {
            return  Varchar.prototype.prepareMeasureData.call(this, this.setDataWithOriginalName());
        },

        getAttributeList() {
            return Varchar.prototype.getAttributeList.call(this)
        },

        validateRequired() {
            return Varchar.prototype.validateRequired.call(this);
        },

        setDataWithOriginalName() {
            const data = Dep.prototype.data.call(this);
            const value = isNaN(this.model.get(this.originalName)) ? null : this.model.get(this.originalName);
            data.value = Dep.prototype.formatNumber.call(this, value);

            if (this.model.get(this.originalName) !== null && typeof this.model.get(this.originalName) !== 'undefined') {
                data.isNotEmpty = true;
            }
            data.valueIsSet = this.model.has(this.originalName);

            return data
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            Varchar.prototype.addMeasureDataOnFetch.call(this, data)
            return data;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.initUnitSelector();
        },

        initUnitSelector() {
            Varchar.prototype.initUnitSelector.call(this);
        },

        getLockedFieldName() {
            return Varchar.prototype.getLockedFieldName.call(this);
        },

        filterInput(rule, inputName) {
            const viewKey = inputName + this.type;
            if (!rule || !inputName) {
                return '';
            }
            if (!this.isNotListeningToOperatorChange) {
                this.isNotListeningToOperatorChange = {};
            }

            if (!this.isNotListeningToOperatorChange[inputName]) {
                this.listenTo(this.model, 'afterUpdateRuleOperator', (rule, previous) => {
                    if (rule.$el.find('.rule-value-container > input').attr('name') !== inputName) {
                        return;
                    }
                    rule.rightValue = null;
                    rule.leftValue = null;

                    let view = this.getView(viewKey);

                    if (!['is_null', 'is_not_null'].includes(rule.operator.type)) {
                        if (rule.operator.type !== 'between' && view) {
                            this.filterValue = [view.model.get('value'), view.model.get('valueUnitId')];
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        }
                    }

                    this.previousOperatorType = rule.operator.type;
                    this.isNotListeningToOperatorChange[inputName] = true;
                })
            }
            let createValueField = (type) => this.getModelFactory().create(null, model => {
                setTimeout(() => {
                    this.previousOperatorType = type ?? rule.operator.type;
                    let view = 'views/fields/unit-' + this.type;

                    this.createView(viewKey, view, {
                        name: 'value',
                        el: `#${rule.id} .field-container.${inputName}`,
                        model: model,
                        mode: 'edit',
                        params: {
                            notNull: true,
                            measureId: this.measureId || this.defs.params?.attribute?.measureId
                        }
                    }, view => {
                        view.render();
                        this.listenTo(model, 'change', () => {
                            if(!rule.data) {
                                rule.data = {}
                            }
                            rule.data.unitField = true;
                            if (rule.operator.type === 'between') {
                                let unitValue = [model.get('value'), model.get('valueUnitId')];
                                if (inputName.endsWith('value_1')) {
                                    rule.rightValue = unitValue;
                                } else {
                                    rule.leftValue = unitValue;
                                }

                                if (rule.rightValue != null && rule.leftValue != null) {
                                    this.filterValue = [rule.leftValue, rule.rightValue];
                                }
                            } else {
                                this.filterValue = [model.get('value'), model.get('valueUnitId')];
                            }
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });
                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });
                }, 50);
                this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                    if (rule.operator.type === 'between' && Array.isArray(rule.value) && rule.value.length === 2) {
                        let entry = inputName.endsWith('value_1') ? rule.value[1] : rule.value[0];
                        rule.leftValue = rule.value[0];
                        rule.rightValue = rule.value[1];
                        if (Array.isArray(entry) && entry.length === 2) {
                            model.set('value', entry[0]);
                            model.set('valueUnitId', entry[1]);
                        }
                    } else if (Array.isArray(rule.value) && rule.value.length === 2) {
                        model.set('value', rule.value[0]);
                        model.set('valueUnitId', rule.value[1]);
                    }
                });
            });

            createValueField();

            return `<div class="field-container ${inputName}"></div><input type="hidden" real-name="${viewKey}" name="${inputName}" />`;
        },

        queryBuilderValidation() {
            return {
                callback: function (value, rule) {
                    if (rule.operator.type === 'between') {
                        if (!Array.isArray(value) || value.length !== 2
                            || !Array.isArray(value[0]) || value[0].length !== 2
                            || !Array.isArray(value[1]) || value[1].length !== 2) {
                            return 'bad between';
                        }
                        if (value[0][0] === null || value[0][0] === '' || !value[0][1]) {
                            return 'bad value';
                        }
                        if (value[1][0] === null || value[1][0] === '' || !value[1][1]) {
                            return 'bad value';
                        }
                        return true;
                    }

                    if (!Array.isArray(value) || value.length !== 2) {
                        return 'bad value';
                    }

                    if (value[0] === null || value[0] === '' || !value[1]) {
                        return 'bad value';
                    }

                    return true;
                }.bind(this),
            }
        }

    });
});
