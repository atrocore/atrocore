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

Espo.define('view', [], function () {

    return Bull.View.extend({

        addActionHandler: function (action, handler) {
            this.events = this.events || {};

            var fullAction = 'click button[data-action=\"' + action + '\"]';
            this.events[fullAction] = handler;
        },

        notify: function (label, type, timeout, scope) {
            if (label == false) {
                Espo.Ui.notify(false);
                return;
            }
            scope = scope || null;
            timeout = timeout || 2000;
            if (!type) {
                timeout = null;
            }
            var text = this.getLanguage().translate(label, 'labels', scope);
            Espo.Ui.notify(text, type, timeout);
        },

        getHelper: function () {
            return this._helper;
        },

        getUser: function () {
            if (this._helper) {
                return this._helper.user;
            }
        },

        getPreferences: function () {
            if (this._helper) {
                return this._helper.preferences;
            }
        },

        getConfig: function () {
            if (this._helper) {
                return this._helper.settings;
            }
        },

        getAcl: function () {
            if (this._helper) {
                return this._helper.acl;
            }
        },

        getModelFactory: function () {
            if (this._helper) {
                return this._helper.modelFactory;
            }
        },

        getCollectionFactory: function () {
            if (this._helper) {
                return this._helper.collectionFactory;
            }
        },

        getRouter: function () {
            if (this._helper) {
                return this._helper.router;
            }
        },

        getSessionStorage: function () {
            if (this._helper) {
                return this._helper.sessionStorage;
            }
        },

        getLanguage: function () {
            if (this._helper) {
                return this._helper.language;
            }
        },

        getMetadata: function () {
            if (this._helper) {
                return this._helper.metadata;
            }
        },

        getCache: function () {
            if (this._helper) {
                return this._helper.cache;
            }
        },

        getStorage: function () {
            if (this._helper) {
                return this._helper.storage;
            }
        },

        getDateTime: function () {
            if (this._helper) {
                return this._helper.dateTime;
            }
        },

        getFieldManager: function () {
            if (this._helper) {
                return this._helper.fieldManager;
            }
        },

        getBaseController: function () {
            if (this._helper) {
                return this._helper.baseController;
            }
        },

        getThemeManager: function () {
            if (this._helper) {
                return this._helper.themeManager;
            }
        },

        updatePageTitle: function () {
            this.setPageTitle('');
        },

        setPageTitle: function (title) {
            let prefix = this.getConfig().get('applicationName') || '';

            if (title && prefix) {
                prefix += ' | ';
            }

            const resultTitle = title ? prefix + title : prefix;

            $('head title').text(resultTitle);
            $('#title-bar .title-container .title').text(resultTitle);
        },

        translate: function (label, category, scope) {
            return this.getLanguage().translate(label, category, scope);
        },

        getBasePath: function () {
            return this._helper.basePath || '';
        },

        ajaxRequest: function (url, type, data, options) {
            var options = options || {};
            options.type = type;
            options.url = url;
            options.context = this;

            if (data) {
                options.data = data;
            }

            var xhr = $.ajax(options);

            return xhr;

            var obj = {
                then: xhr.then,
                fail: xhr.fail,
                catch: xhr.fail
            };

            return obj;
        },

        ajaxPostRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }
            return this.ajaxRequest(url, 'POST', data, options);
        },

        ajaxPatchRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }
            return this.ajaxRequest(url, 'PATCH', data, options);
        },

        ajaxPutRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }
            return this.ajaxRequest(url, 'PUT', data, options);
        },

        ajaxGetRequest: function (url, data, options) {
            return this.ajaxRequest(url, 'GET', data, options);
        },

        confirm: function (o, callback, context) {
            var confirmStyle = null;
            if (typeof o === 'string' || o instanceof String) {
                var message = o;
                var confirmText = this.translate('Yes');
            } else {
                o = o || {};
                var message = o.message;
                var confirmText = o.confirmText;
                confirmStyle = o.confirmStyle || null;
            }
            Espo.Ui.confirm(message, {
                confirmText: confirmText,
                cancelText: this.translate('Cancel'),
                confirmStyle: confirmStyle
            }, callback, context);
        },
        copyToClipboard(text, callback) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                // Clipboard API method
                navigator.clipboard.writeText(text).then(function () {
                    if (callback) {
                        callback(true);
                    }
                }, function (err) {
                    console.error('Could not copy text: ', err);
                    if (callback) {
                        callback(false);
                    }
                });
            } else {
                // Fallback method
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";  // Avoid scrolling to bottom
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    if (callback) {
                        callback(true);
                    }
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                    if (callback) {
                        callback(false);
                    }
                }

                document.body.removeChild(textArea);
            }
        },

        simpleHash(str) {
            let hash = 5381;
            for (let i = 0; i < str.length; i++) {
                hash = (hash * 33) ^ str.charCodeAt(i);
            }
            return hash >>> 0; // Ensure it's a 32-bit unsigned integer
        },

        getTabIcon(scope) {
            let iconClass = this.getMetadata().get(['clientDefs', scope, 'iconClass']) || null;

            if (iconClass) {
                const systemIcons = this.getMetadata().get(['app', 'systemIcons']) || [];

                if (systemIcons && iconClass in systemIcons) {
                    const path = systemIcons[iconClass].path || null;

                    if (path) {
                        return systemIcons[iconClass].path;
                    }
                }
            }

            return null;
        },

        getDefaultTabIcon(scope) {
            let firstSymbol = scope.match(/\p{L}/u)?.[0] || null,
                key = null;

            if (firstSymbol) {
                if (Number.isInteger(firstSymbol)) {
                    key = firstSymbol + '-numbers-icon.svg';
                } else {
                    key = firstSymbol.toLowerCase() + '-alphabet-icon.svg';
                }

                return 'client/img/icons/default/' + key;
            }

            return null;
        },

        getAdminBreadcrumbsItem: function () {
            return {
                url: '#Admin',
                label: this.getLanguage().translate('Administration', 'labels')
            };
        },

        addToLanguageObservables: function () {
            if (!window.languageObservableViews) {
                window.languageObservableViews = new Map();
            }

            window.languageObservableViews.set(this.cid, this);

            this.once('remove', () => {
                window.languageObservableViews?.delete(this.cid);
            });
        },

        getLocalizedFieldData(scope, fieldName) {
            if (this.getMetadata().get(`entityDefs.${scope}.fields.${fieldName}.isMultilang`) === true) {
                const user = this.getUser()
                const locales = this.getConfig().get('locales') || {}
                let userLocale = null;
                if (this.getLanguage().localeId) {
                    userLocale = locales[this.getLanguage().localeId]
                } else {
                    userLocale = locales[user.get('localeId')]
                    if (!userLocale) {
                        userLocale = locales[this.getConfig().get('locale')]
                    }
                }


                let mainLocaleCode = ''
                let userLocaleCode = ''
                let field = fieldName

                for (const [code, language] of Object.entries(this.getConfig().get('referenceData').Language ?? {})) {
                    if (language.role === 'main') {
                        mainLocaleCode = code
                    }
                    if (code === userLocale?.language) {
                        userLocaleCode = code
                    }
                }

                if (userLocaleCode && userLocaleCode !== mainLocaleCode) {
                    field += userLocaleCode.split('_').map(part => Espo.utils.upperCaseFirst(part.toLowerCase())).join('')
                }

                return [field, userLocaleCode]
            }

            return [fieldName, null]
        },

        getNameField($scope) {
            return this.getMetadata().get(`scopes.${$scope}.nameField`) ?? 'name'
        },

        getLocalizedFieldValue(model, fieldName) {
            let [localizedFieldName] = this.getLocalizedFieldData(model.name, fieldName);
            if (localizedFieldName !== fieldName) {
                return model.get(localizedFieldName) || model.get(fieldName);
            }

            return model.get(fieldName);
        },

        getModelTitle(){
          return this.getLocalizedFieldValue(this.model, this.model.nameField)
        },

        onModelReady(callback) {
            const modelIsSynced = !!this.model.attributes?.id;

            if (this.model.isNew() || !this.model.hasField('id') || modelIsSynced) {
                callback();
            } else {
                this.listenToOnce(this.model, 'sync', () => {
                    callback(true);
                });
            }
        },

        initSelectizeClearPlugin() {
            const clearTitle = this.translate('Clear');
            Selectize.define('clear_button', function (options) {
                const self = this;

                function appendButton() {
                    if (!self.getValue()) {
                        self.$control.find('.clear-button').remove();
                        return;
                    }

                    if (self.$control.find('.clear-button').length) return;
                    const $clearButton = $(`<a href="javascript:" class="clear-button" title="${clearTitle}"><i class="ph ph-minus"></i></a>`);
                    $clearButton.on('click', function (e) {
                        e.preventDefault();
                        self.clear();
                    });
                    self.$control.append($clearButton);
                }

                this.setup = (function () {
                    const original = self.setup;
                    return function () {
                        original.apply(this, arguments);

                        appendButton();
                        this.on('change', appendButton);
                        this.on('clear', appendButton);
                        this.on('item_add', appendButton);
                        this.on('item_remove', appendButton);
                    };
                })();
            });
        },

        logToNavigationHistory(name) {
            if (!name) {
                return;
            }

            this.ajaxPostRequest('App/logNavigation/' + name,
                {
                    url: window.location.pathname + (window.location.hash || '#')
                },
                {
                    headers: {
                        'Entity-History': sessionStorage.tabId || 'true'
                    }
                }
            );
        },

        getAppDisplayMode() {
            if (document.referrer.startsWith('android-app://'))
                return 'twa';
            if (window.matchMedia('(display-mode: browser)').matches)
                return 'browser';
            if (window.matchMedia('(display-mode: standalone)').matches)
                return 'standalone';
            if (window.matchMedia('(display-mode: minimal-ui)').matches)
                return 'minimal-ui';
            if (window.matchMedia('(display-mode: fullscreen)').matches)
                return 'fullscreen';
            if (window.matchMedia('(display-mode: window-controls-overlay)').matches)
                return 'window-controls-overlay';

            return 'unknown';
        },

        isPWAMode: function () {
            return ~['window-controls-overlay', 'standalone'].indexOf(this.getAppDisplayMode());
        },

        getFavicon: function () {
            const faviconId = this.getConfig().get('faviconId');
            if (faviconId) {
                return `/?entryPoint=LogoImage&id=${faviconId}`;
            }

            return 'client/modules/treo-core/img/favicon.svg';
        },
    });

});
