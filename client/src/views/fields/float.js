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

Espo.define('views/fields/float', 'views/fields/int', function (Dep) {

    return Dep.extend({

        type: 'float',

        editTemplate: 'fields/float/edit',

        validations: ['required', 'float', 'range'],

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

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'float',
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
                input: this.filterInput,
                valueGetter: this.filterValueGetter
            };
        },

    });
});
