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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('treo-core:views/fields/email', 'class-replace!treo-core:views/fields/email',

    Dep => Dep.extend({

        validateEmailData: function () {
            var data = this.model.get(this.dataFieldName);
            if (data && data.length) {
                var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                var notValid = false;
                data.forEach(function (row, i) {
                    var emailAddress = row.emailAddress;
                    if (!re.test(emailAddress) && emailAddress.indexOf(this.erasedPlaceholder) !== 0) {
                        var msg = this.translate('fieldShouldBeEmail', 'messages').replace('{field}', this.getLabelText());
                        this.showValidationMessage(msg, 'div.email-address-block:nth-child(' + (i + 1).toString() + ') input');
                        this.setErrorClass('div.email-address-block:nth-child(' + (i + 1).toString() + ') input');
                        notValid = true;
                    }
                }, this);
                if (notValid) {
                    return true;
                }
            }
        },
        validateRequired: function () {
            if (this.isRequired()) {
                if (!this.model.get(this.name) || !this.model.get(this.name) === '') {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, 'div.email-address-block:nth-child(1) input');
                    this.setErrorClass('div.email-address-block:nth-child(1) input');
                    return true;
                }
            }
        },
        setErrorClass(selector) {
            let input = this.$el.find(selector);
            input.addClass('email-data-error');

            this.$el.on('click', () => {
                input.removeClass('email-data-error');
                this.$el.off('click');
            });
        }
    })
);
