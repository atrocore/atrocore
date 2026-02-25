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

Espo.define('views/fields/float', 'views/fields/int', function (Dep) {

    return Dep.extend({

        type: 'float',

        editTemplate: 'fields/float/edit',

        validations: ['required', 'float', 'range'],

        onInlineEditSave(res, attrs, model) {
            attrs[this.name] = res[this.name] || null

            Dep.prototype.onInlineEditSave.call(this, res, attrs, model);
        },

        getValueForDisplay: function () {
            var value = isNaN(this.model.get(this.name)) ? null : this.model.get(this.name);
            return this.formatNumber(value);
        },

        formatNumber: function (value) {
            if (this.disableFormatting) {
                return value;
            }

            if (value !== null) {
                var parts = value.toString().split(".");

                if (this.mode !== 'edit') {
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);
                }

                return parts.join(this.decimalMark);
            }

            return '';
        },

        validateFloatByValue: function (value) {
            if (!value || value.length === 0) {
                return {
                    message: null,
                    invalid: false
                };
            }

            let invalid = false;

            let pattern = "^-?[0-9]\\d*(\\" + this.decimalMark + "\\d+)?$";
            let matcher = new RegExp(pattern);
            if (!matcher.test(value.replaceAll(this.thousandSeparator, ''))) {
                invalid = true;
            }

            if (!invalid && (value.match(new RegExp("\\" + this.decimalMark, "g")) || []).length > 1) {
                invalid = true;
            }

            if (!invalid && this.thousandSeparator && value.indexOf(this.thousandSeparator) >= 0) {
                pattern = "^-?\\d{1,3}(\\" + this.thousandSeparator + "\\d{3})*(\\" + this.decimalMark + "\\d+)?$";
                matcher = new RegExp(pattern);
                if (!matcher.test(value)) {
                    invalid = true;
                }
            }

            if (invalid) {
                return {
                    message: this.translate('fieldShouldBeFloat', 'messages').replace('{field}', this.getLabelText()),
                    invalid: true
                };
            }

            return {
                message: null,
                invalid: false
            };
        },

        validateFloat: function () {
            let value = this.$el.find('[name="' + this.name + '"]').val();
            let res = this.validateFloatByValue(value);

            if (res.invalid) {
                this.showValidationMessage(res.message);
            }

            return res.invalid;
        },

        parse: function (value) {
            value = (value !== '') ? value : null;
            if (value) {
                value = value.split(this.thousandSeparator).join('');
                value = value.split(this.decimalMark).join('.');
                value = parseFloat(value);
            }
            return value;
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


                    if (!['is_null', 'is_not_null', 'current_month', 'last_month', 'next_month', 'current_year', 'last_year'].includes(rule.operator.type)) {
                        if (rule.operator.type !== 'between' && view) {
                            this.filterValue = view.model.get('value');
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        }

                        if (['last_x_days', 'next_x_days', 'older_than_x_days', 'after_x_days'].includes(this.previousOperatorType) && !['last_x_days', 'next_x_days', 'older_than_x_days', 'after_x_days'].includes(rule.operator.type)) {
                            createValueField(rule.operator.type)
                        }

                        if (!['last_x_days', 'next_x_days', 'older_than_x_days', 'after_x_days'].includes(this.previousOperatorType) && ['last_x_days', 'next_x_days', 'older_than_x_days', 'after_x_days'].includes(rule.operator.type)) {
                            createValueField(rule.operator.type)
                        }
                    } else {
                        rule.value = this.defaultFilterValue;
                        if (view) {
                            view.model.set('value', this.defaultFilterValue);
                        }
                    }
                    this.previousOperatorType = rule.operator.type;
                    this.isNotListeningToOperatorChange[inputName] = true;
                })
            }
            this.filterValue = this.defaultFilterValue;
            let createValueField = (type) => this.getModelFactory().create(null, model => {
                model.set('value', this.defaultFilterValue);
                setTimeout(() => {
                    this.previousOperatorType = type ?? rule.operator.type;
                    let view = `views/fields/${this.type}`

                    if (['wysiwyg', 'markdown', 'text'].includes(this.type)) {
                        view = 'views/fields/varchar';
                    } else if (this.type === 'autoincrement') {
                        view = 'views/fields/int';
                    }

                    if(model.getFieldParam(this.name, 'measureId')) {
                        view = `views/fields/unit-${this.type}`
                    }

                    this.createView(viewKey, view, {
                        name: 'value',
                        el: `#${rule.id} .field-container.${inputName}`,
                        model: model,
                        mode: 'edit',
                        params: {
                            notNull: true
                        }
                    }, view => {
                        view.render();
                        this.listenTo(model, 'change', () => {
                            if (rule.operator.type === 'between') {
                                if (inputName.endsWith('value_1')) {
                                    rule.rightValue = model.get('value')
                                } else {
                                    rule.leftValue = model.get('value')
                                }

                                if (rule.rightValue != null && rule.leftValue != null) {
                                    this.filterValue = [rule.leftValue, rule.rightValue];
                                }
                            } else {
                                this.filterValue = model.get('value')
                            }
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });
                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });
                }, 50);
                this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                    if (rule.operator.type === 'between' && Array.isArray(rule.value) && rule.value.length === 2) {
                        rule.leftValue = rule.value[0];
                        rule.rightValue = rule.value[1];
                        model.set('value', inputName.endsWith('value_1') ? rule.value[1] : rule.value[0]);
                    } else {
                        model.set('value', rule.value);
                    }
                });
            });

            createValueField();

            return `<div class="field-container ${inputName}"></div><input type="hidden" real-name="${viewKey}" name="${inputName}" />`;
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'double',
                optgroup: this.getLanguage().translate('Fields'),
                operators: [
                    'equal',
                    'not_equal',
                    'less',
                    'less_or_equal',
                    'greater',
                    'greater_or_equal',
                    'between',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function (value, rule) {
                        if (rule.operator.type === 'between') {
                            if ((!Array.isArray(value) || value.length !== 2)) {
                                return 'bad between';
                            }
                            return true;
                        }

                        if (isNaN(value) || value === null) {
                            return 'bad float';
                        }

                        return true;
                    }.bind(this),
                }
            };
        },

    });
});
