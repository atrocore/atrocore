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

Espo.define('views/fields/range-int', ['views/fields/base', 'views/fields/int'], function (Dep, Int) {

    return Dep.extend({

        type: 'rangeInt',

        listTemplate: 'fields/range-int/detail',

        detailTemplate: 'fields/range-int/detail',

        editTemplate: 'fields/range-int/edit',

        validations: ['required', 'int', 'range', 'order'],

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.name = this.name;
            data.ucName = Espo.Utils.upperCaseFirst(this.name);
            data.fromValue = this.model.get(this.fromField);
            data.toValue = this.model.get(this.toField);
            data.isNull = (isNaN(data.fromValue) || data.fromValue === null) && (isNaN(data.toValue) || data.toValue === null)

            if (this.measureId) {
                data.unitFieldName = this.unitFieldName;
                data.unitList = this.unitList;
                data.unitListTranslates = this.unitListTranslates;
                data.unitValue = this.model.get(this.unitFieldName);
                data.unitValueTranslate = this.unitListTranslates[data.unitValue] || data.unitValue;
            }

            return data;
        },

        init: function () {
            let fieldName = this.options.name || this.options.defs.name;

            this.rangeField = fieldName;
            this.fromField = fieldName + 'From';
            this.toField = fieldName + 'To';

            Dep.prototype.init.call(this);
        },

        getValueForDisplay: function () {
            var fromValue = this.model.get(this.fromField);
            var toValue = this.model.get(this.toField);

            var fromValue = isNaN(fromValue) ? null : fromValue;
            var toValue = isNaN(toValue) ? null : toValue;

            if (fromValue !== null && toValue !== null) {
                return this.formatNumber(fromValue) + ' &#8211 ' + this.formatNumber(toValue);
            } else if (fromValue) {
                return '&#62;&#61; ' + this.formatNumber(fromValue);
            } else if (toValue) {
                return '&#60;&#61; ' + this.formatNumber(toValue);
            } else {
                return this.translate('None');
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.getPreferences().has('decimalMark')) {
                this.decimalMark = this.getPreferences().get('decimalMark');
            } else {
                if (this.getConfig().has('decimalMark')) {
                    this.decimalMark = this.getConfig().get('decimalMark');
                }
            }
            if (this.getPreferences().has('thousandSeparator')) {
                this.thousandSeparator = this.getPreferences().get('thousandSeparator');
            } else {
                if (this.getConfig().has('thousandSeparator')) {
                    this.thousandSeparator = this.getConfig().get('thousandSeparator');
                }
            }

            if (this.measureId) {
                this.unitFieldName = this.name + 'UnitId';
                this.loadUnitOptions();
                if (this.model.isNew() && this.defaultUnit) {
                    this.model.set(this.unitFieldName, this.defaultUnit);
                }
            }
        },

        translate: function (name, category, scope) {
            if (category === 'fields' && scope === this.model.name) {
                let attributeLabel = this.model.getFieldParam(this.name, 'label');
                if (attributeLabel) {
                    if (name === this.name + 'To') {
                        return Dep.prototype.translate.call(this, 'To', category, scope);
                    }
                    if (name === this.name + 'From') {
                        return Dep.prototype.translate.call(this, 'From', category, scope);
                    }
                }
            }

            return Dep.prototype.translate.call(this, name, category, scope);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode == 'edit') {
                this.$from = this.$el.find('[name="' + this.fromField + '"]');
                this.$to = this.$el.find('[name="' + this.toField + '"]');

                this.$from.on('change', function () {
                    this.trigger('change');
                }.bind(this));
                this.$to.on('change', function () {
                    this.trigger('change');
                }.bind(this));
            }
        },

        validateRequired: function () {
            var validate = function (name) {
                if (this.isRequired()) {
                    if (this.model.get(name) === null) {
                        var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                        this.showValidationMessage(msg, '[name="' + name + '"]');
                        return true;
                    }
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        validateInt: function () {
            var validate = function (name) {
                if (isNaN(this.model.get(name))) {
                    var msg = this.translate('fieldShouldBeInt', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, '[name="' + name + '"]');
                    return true;
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        validateRange: function () {
            var validate = function (name) {
                var value = this.model.get(name);

                if (value === null) {
                    return false;
                }

                var minValue = this.model.getFieldParam(name, 'min');
                var maxValue = this.model.getFieldParam(name, 'max');

                if (minValue !== null && maxValue !== null) {
                    if (value < minValue || value > maxValue) {
                        var msg = this.translate('fieldShouldBeBetween', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name))
                            .replace('{min}', minValue)
                            .replace('{max}', maxValue);
                        this.showValidationMessage(msg, '[name="' + name + '"]');
                        return true;
                    }
                } else {
                    if (minValue !== null) {
                        if (value < minValue) {
                            var msg = this.translate('fieldShouldBeLess', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name))
                                .replace('{value}', minValue);
                            this.showValidationMessage(msg, '[name="' + name + '"]');
                            return true;
                        }
                    } else if (maxValue !== null) {
                        if (value > maxValue) {
                            var msg = this.translate('fieldShouldBeGreater', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name))
                                .replace('{value}', maxValue);
                            this.showValidationMessage(msg, '[name="' + name + '"]');
                            return true;
                        }
                    }
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        validateOrder: function () {
            var fromValue = this.model.get(this.fromField);
            var toValue = this.model.get(this.toField);

            if (fromValue !== null && toValue !== null) {
                if (fromValue > toValue) {
                    var msg = this.translate('fieldShouldBeGreater', 'messages').replace('{field}', this.translate(this.toField, 'fields', this.model.name))
                        .replace('{value}', this.translate(this.fromField, 'fields', this.model.name));

                    this.showValidationMessage(msg, '[name="' + this.fromField + '"]');
                    return true;
                }
            }
        },

        parse: function (value) {
            return Int.prototype.parse.call(this, value);
        },

        formatNumber: function (value) {
            return Int.prototype.formatNumber.call(this, value);
        },

        isInheritedField: function () {
            if (!['detail', 'edit'].includes(this.mode) || !this.model || !this.model.urlRoot || !this.isInheritableField()) {
                return false;
            }

            const inheritedFields = this.model.get('inheritedFields');
            if (!inheritedFields || !Array.isArray(inheritedFields)) {
                return false;
            }

            let res = inheritedFields.includes(this.name + 'From') && inheritedFields.includes(this.name + 'To');
            if (this.measureId) {
                res = res && inheritedFields.includes(this.name + 'Unit');
            }

            return res;
        },

        fetch: function (form) {
            let data = {};

            let $from = this.$el.find('[name="' + this.fromField + '"]');
            if ($from) {
                data[this.fromField] = this.parse(($from.val() || '').trim());
            }

            let $to = this.$el.find('[name="' + this.toField + '"]');
            if ($to) {
                data[this.toField] = this.parse(($to.val() || '').trim());
            }

            if (this.measureId) {
                let $unit = this.$el.find(`[name="${this.unitFieldName}"]`);
                data[this.unitFieldName] = $unit ? $unit.val() : null;
            }

            return data;
        }

    });
});

