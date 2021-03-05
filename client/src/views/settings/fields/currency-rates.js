
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

Espo.define('views/settings/fields/currency-rates', 'views/fields/float', function (Dep) {

    return Dep.extend({

        editTemplate: 'settings/fields/currency-rates/edit',

        data: function () {
            var baseCurrency = this.model.get('baseCurrency');
            var currencyRates = this.model.get('currencyRates') || {};

            var rateValues = {};
            (this.model.get('currencyList') || []).forEach(function (currency) {
                if (currency != baseCurrency) {
                    let value = currencyRates[currency];
                    if (!value) {
                        if (currencyRates[baseCurrency]) {
                            value = Math.round(1 / currencyRates[baseCurrency] * 1000) / 1000;
                        }
                        if (!value) {
                            value = 1.00
                        }
                    }

                    rateValues[currency] = this.formatNumber(value);
                }
            }, this);

            return {
                rateValues: rateValues,
                baseCurrency: baseCurrency
            };
        },

        fetch: function () {
            var data = {};
            var currencyRates = {};

            var baseCurrency = this.model.get('baseCurrency');

            var currencyList = this.model.get('currencyList') || [];

            currencyList.forEach(function (currency) {
                if (currency != baseCurrency) {
                    currencyRates[currency] = this.parse(this.$el.find('input[data-currency="'+currency+'"]').val());
                }
            }, this);

            delete currencyRates[baseCurrency];
            for (var c in currencyRates) {
                if (!~currencyList.indexOf(c)) {
                    delete currencyRates[c];
                }
            }

            data[this.name] = currencyRates;

            return data;
        },

        validateFloat: function () {
            var rates = this.model.get(this.name);

            (Object.values(rates) || []).forEach(rate => {
                if (isNaN(rate)) {
                    var msg = this.translate('fieldShouldBeFloat', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            })
        }
    });

});
