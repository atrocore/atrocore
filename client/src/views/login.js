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

Espo.define('views/login', 'view', function (Dep) {

    return Dep.extend({

        template: 'login',

        localeId: 'default',

        theme: 'default',

        views: {
            footer: {
                el: 'body > footer',
                view: 'views/site/footer'
            },
        },

        setup() {
            Dep.prototype.setup.call(this);

            let localeId = 'default';
            if (localeId && (this.getConfig().get('locales')[localeId] || localeId === 'default')) {
                this.localeId = localeId;
                this.ajaxGetRequest('I18n', {locale: localStorage.getItem('language') || 'default'}, {async: false}).then(data => {
                    this.getLanguage().data = data;
                });
            } else {
                this.localeId = this.getConfig().get('localeId');
            }
        },

        afterRender: function () {
            let demo = this.getConfig().get('demo') || {"username": "", "password": ""};
            $('#field-userName').val(demo.username);
            $('#field-password').val(demo.password);

            // setup background image
            this.setupBackgroundImage();
        },

        setupBackgroundImage: function () {
            $.ajax({
                url: 'background?silent=true',
                type: 'GET',
                success: data => {
                    const $body = $('body');
                    $body.children('.content').css({'height': 'calc(100% - 28px)'});
                    $body.append('<span class="photo-link">Photo by <a href="' + data.authorLink + '" target="_blank">' + data.authorName + '</a></span>');
                }
            });
        },

        events: {
            'submit #login-form': function (e) {
                this.login();
                return false;
            },
            'click a[data-action="passwordChangeRequest"]': function (e) {
                this.showPasswordChangeRequest();
            },
            'change select[name="locale"]': function (event) {
                this.localeId = $(event.currentTarget).val();
                if (this.localeId) {
                    let language = $("#locale option:selected").data('language');
                    this.ajaxGetRequest('I18n', {locale: language}).then(data => {
                        this.getLanguage().data = data;
                        this.reRender();
                        localStorage.setItem('localeId', this.localeId);
                        localStorage.setItem('language', language);
                    });
                }
            },
            'change select[name="theme"]': function (event) {
                this.theme = $(event.currentTarget).val();
            }
        },

        data: function () {
            return {
                locales: this.getLocales(),
                themes: this.getThemes(),
                logoSrc: this.getLogoSrc()
            };
        },

        getLogoSrc: function () {
            const companyLogoId = this.getConfig().get('companyLogoId');
            if (!companyLogoId) {
                return this.getBasePath() + 'client/modules/treo-core/img/core_logo_dark.svg';
            }
            return this.getBasePath() + '?entryPoint=LogoImage&id=' + companyLogoId + '&t=' + companyLogoId;
        },

        getLocales() {
            let result = [];
            $.each((this.getConfig().get('locales') || {}), (id, locale) => {
                result.push({
                    value: id,
                    label: locale.name,
                    language: locale.language,
                    selected: id === this.localeId
                });
            });

            result.unshift({
                value: 'default',
                label: this.translate('Default', 'labels', 'Global'),
                language: this.getConfig().get('language'),
                selected: 'default' === this.localeId,
            });

            return result;
        },

        getThemes() {
            let themes = Object.keys(this.getConfig().get('themes') || {}).map(theme => {
                return {
                    name: theme,
                    label: this.translate(theme, 'themes', 'Global')
                }
            });

            themes.unshift({
                name: this.theme,
                label: this.translate('Default', 'labels', 'Global')
            });

            return themes;
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
                    'Authorization': 'Basic ' + Base64.encode(userName + ':' + password)
                },
                success: function (data) {
                    this.notify(false);

                    let localeId = $("#locale option:selected").val();

                    let requestData = {};
                    requestData['locale'] = localeId === 'default' ? (data.preferences?.locale ?? null) : localeId;
                    requestData['localeId'] = requestData['locale'];

                    if (this.theme !== 'default' && data.preferences.theme !== this.theme) {
                        requestData['theme'] = this.theme;
                    }

                    const response = $.ajax({
                        url: 'Preferences/' + data.user.id,
                        method: 'PUT',
                        headers: {
                            'Authorization-Token': Base64.encode(userName + ':' + data.token)
                        },
                        data: JSON.stringify(requestData),
                        async: false
                    });
                    if (response && response.responseJSON) {
                        data.preferences = response.responseJSON
                    }

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

        onWrong: function () {
            var cell = $('#login .form-group');
            cell.addClass('has-error');
            this.$el.one('mousedown click', function () {
                cell.removeClass('has-error');
            });
            Espo.Ui.error(this.translate('wrongUsernamePasword', 'messages', 'User'));
        },

        showPasswordChangeRequest: function () {
            this.notify('Please wait...');
            this.createView('passwordChangeRequest', 'views/modals/password-change-request', {
                url: window.location.href
            }, function (view) {
                view.render();
                view.notify(false);
            });
        }
    });

});
