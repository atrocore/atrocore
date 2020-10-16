

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

Espo.define('treo-core:views/login', 'class-replace!treo-core:views/login',
    Dep => Dep.extend({

        template: 'treo-core:login',

        language: null,

        events: _.extend({
            'change select[name="language"]': function (event) {
                this.language = $(event.currentTarget).val();
                if (this.language) {
                    this.ajaxGetRequest('I18n', {locale: this.language}).then((data) => {
                        this.getLanguage().data = data;
                        this.reRender();
                    });
                }
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            this.language = this.getConfig().get('language');
        },

        data() {
            return _.extend({
                locales: this.getLocales()
            }, Dep.prototype.data.call(this));
        },

        getLogoSrc: function () {
            const companyLogoId = this.getConfig().get('companyLogoId');
            if (!companyLogoId) {
                return this.getBasePath() + 'client/modules/treo-core/img/core_logo_white.svg';
            }
            return this.getBasePath() + '?entryPoint=LogoImage&id='+companyLogoId+'&t=' + companyLogoId;
        },

        getLocales() {
            let translatedOptions = Espo.Utils.clone(this.getLanguage().translate('language', 'options') || {});

            return Espo.Utils
                .clone(this.getConfig().get('languageList')).sort((v1, v2) => this.getLanguage().translateOption(v1, 'language').localeCompare(this.getLanguage().translateOption(v2, 'language'))                )
                .map(item => {
                    return {
                        value: item,
                        label: translatedOptions[item],
                        selected: item === this.language
                    };
                });
        },

        login: function () {
            var userName = $('#field-userName').val();
            var trimmedUserName = userName.trim();
            if (trimmedUserName !== userName) {
                $('#field-userName').val(trimmedUserName);
                userName = trimmedUserName;
            }

            var password = $('#field-password').val();

            var $submit = this.$el.find('#btn-login');

            if (userName == '') {
                var $el = $("#field-userName");

                var message = this.getLanguage().translate('userCantBeEmpty', 'messages', 'User');
                $el.popover({
                    placement: 'bottom',
                    content: message,
                    trigger: 'manual',
                }).popover('show');

                var $cell = $el.closest('.form-group');
                $cell.addClass('has-error');
                this.$el.one('mousedown click', function () {
                    $cell.removeClass('has-error');
                    $el.popover('hide');
                });
                return;
            }

            $submit.addClass('disabled').attr('disabled', 'disabled');

            this.notify('Please wait...');

            $.ajax({
                url: 'App/user',
                headers: {
                    'Authorization': 'Basic ' + Base64.encode(userName  + ':' + password),
                    'Espo-Authorization': Base64.encode(userName + ':' + password),
                    'Espo-Authorization-By-Token': false
                },
                data: {
                    language: this.language
                },
                success: function (data) {
                    this.notify(false);
                    this.trigger('login', {
                        auth: {
                            userName: userName,
                            token: data.token
                        },
                        user: data.user,
                        preferences: data.preferences,
                        acl: data.acl,
                        settings: data.settings,
                        appParams: data.appParams
                    });
                }.bind(this),
                error: function (xhr) {
                    $submit.removeClass('disabled').removeAttr('disabled');
                    if (xhr.status == 401) {
                        this.onWrong();
                    }
                }.bind(this),
                login: true,
            });
        },

   })
);