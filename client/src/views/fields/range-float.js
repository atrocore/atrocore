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

Espo.define('views/fields/range-float', ['views/fields/range-int', 'views/fields/float'], function (Dep, Float) {

    return Dep.extend({

        type: 'rangeFloat',
        validations: ['required', 'float', 'range', 'order'],

        validateFloat: function () {
            let validateFromField = Float.prototype.validateFloatByValue.call(this, this.$el.find('[name="' + this.fromField + '"]').val());
            if (validateFromField.invalid) {
                this.showValidationMessage(validateFromField.message, '[name="' + this.fromField + '"]');
                return true;
            }

            let validateToField = Float.prototype.validateFloatByValue.call(this, this.$el.find('[name="' + this.toField + '"]').val());
            if (validateToField.invalid) {
                this.showValidationMessage(validateToField.message, '[name="' + this.toField + '"]');
                return true;
            }

            return false;
        },

        parse: function (value) {
            return Float.prototype.parse.call(this, value);
        },

        formatNumber: function (value) {
            return Float.prototype.formatNumber.call(this, value);
        },

        data: function () {
            let data = Dep.prototype.data.call(this);

            data.fromValue = isNaN(this.model.get(this.fromField)) ? null : this.formatNumber(this.model.get(this.fromField));
            data.toValue = isNaN(this.model.get(this.toField)) ? null : this.formatNumber(this.model.get(this.toField));

            return data;
        },

    });
});

