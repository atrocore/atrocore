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

        getStorage: function () {
            if (this._helper) {
                return this._helper.storage;
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
            var prefix = this.getConfig().get('applicationName') || '';
            if (title && prefix) {
                prefix += ' | '
            }
            $('head title').text(prefix + title);
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

        setupTourButton() {
            let type = this.mode ?? this.type;

            let urlHashes = window.location.hash.split('/')

            if (this.$el.parent()?.hasClass('panel-body') || !urlHashes.length || urlHashes[0] !== `#${this.scope}`) {
                return;
            }

            this.$el.parent().find('[data-action="showTour"]').remove();

            if (!this.getMetadata().get(['tourData', this.scope, type])) {
                return;
            }

            if (!this.getPreparedTourData(type).length) {
                return;
            }

            let button = $(`<a href='javascript:' style="text-decoration:none" data-action='showTour'><svg class="icon"><use href="client/img/icons/icons.svg#question-circle"></use></svg></a>`);
            button.on('click', () => this.showTour(type))
            this.$el.parent().find('.page-header .header-title').append(button)
        },

        showTour(type) {
            let data = this.getPreparedTourData(type);

            const driver = window.driver({
                showProgress: true,
                steps: data
            });
            driver.drive();
        },

        getPreparedTourData(type) {
            if (this.preparedTourData && this.preparedTourData[type]) {
                return this.preparedTourData[type];
            }else if(!this.preparedTourData){
                this.preparedTourData = {};
            }

            let language = this.getPreferences().get('language') ?? 'en_US';

            let tourData = this.getMetadata().get(['tourData', this.scope, type]);
            if (!tourData) {
                return []
            }
            let preparedData = [];

            Object.entries(tourData).forEach(([key, value]) => {
                if (!('description' in value)) {
                    return true;
                }
                let driverElement = {
                    element: key,
                    popover: {}
                };
                ['title', 'description'].forEach(key => {
                    if ((key in value) && (language in value[key])) {
                        driverElement['popover'][key] = value[key][language] ?? value[key]['en_US'];
                    } else if ((key in value) && ('en_US' in value[key])) {
                        driverElement['popover'][key] = value[key]['en_US'];
                    } else {
                        if (key === 'description') {
                            return true
                        }
                    }
                });

                if ($(key).length && $(key).css('display') !== 'none') {
                    let elementId   = Math.random().toString(36).substring(2, 10);
                    $(key).attr("driver-id", elementId)
                    driverElement.element = `[driver-id="${elementId}"]`;
                    preparedData.push(driverElement);
                }
            });

            return this.preparedTourData = preparedData
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
                const referenceData = this.getConfig().get('referenceData');

                if (referenceData && referenceData['SystemIcon'] && iconClass in referenceData['SystemIcon']) {
                    const path = referenceData['SystemIcon'][iconClass].path || null;

                    if (path) {
                        return referenceData['SystemIcon'][iconClass].path;
                    }
                }
            }

            return null;
        },

        getDefaultTabIcon(scope) {
            let firstLetter = scope.match(/\p{L}/u)?.[0] || null;

            if (firstLetter) {
                let key = firstLetter.toLowerCase() + '-alphabet-icon.svg';

                return 'client/img/icons/default/' + key;
            }

            return null;
        }
    });

});
