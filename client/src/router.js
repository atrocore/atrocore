
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

Espo.define('router', [], function () {

    var Router = Backbone.Router.extend({

        routes: {
            "logout": "logout",
            "clearCache": "clearCache",
            ":controller/view/:id/:options": "view",
            ":controller/view/:id": "view",
            ":controller/edit/:id/:options": "edit",
            ":controller/edit/:id": "edit",
            ":controller/create": "create",
            ":controller/:action/:options": "action",
            ":controller/:action": "action",
            ":controller": "defaultAction",
            "*actions": "home",
        },

        _last: null,

        confirmLeaveOut: false,

        backProcessed: false,

        confirmLeaveOutMessage: 'Are you sure?',

        confirmLeaveOutConfirmText: 'Yes',

        confirmLeaveOutCancelText: 'No',

        initialize: function () {
            this.history = [];

            var detectBackOrForward = function(onBack, onForward) {
                hashHistory = [window.location.hash];
                historyLength = window.history.length;

                return function () {
                    var hash = window.location.hash, length = window.history.length;
                    if (hashHistory.length && historyLength == length) {
                        if (hashHistory[hashHistory.length - 2] == hash) {
                            hashHistory = hashHistory.slice(0, -1);
                            if (onBack) {
                                onBack();
                            }
                        } else {
                            hashHistory.push(hash);
                            if (onForward) {
                                onForward();
                            }
                        }
                    } else {
                        hashHistory.push(hash);
                        historyLength = length;
                    }
                }
            };

            window.addEventListener('hashchange', detectBackOrForward(function () {
                this.backProcessed = true;
                setTimeout(function () {
                    this.backProcessed = false;
                }.bind(this), 50);
            }.bind(this)));

            this.on('route', function () {
                this.history.push(Backbone.history.fragment);
            });
        },

        getCurrentUrl: function () {
            return '#' + Backbone.history.fragment;
        },

        checkConfirmLeaveOut: function (callback, context, navigateBack) {
            context = context || this;
            if (this.confirmLeaveOut) {
                Espo.Ui.confirm(this.confirmLeaveOutMessage, {
                    confirmText: this.confirmLeaveOutConfirmText,
                    cancelText: this.confirmLeaveOutCancelText,
                    cancelCallback: function () {
                        if (navigateBack) {
                            this.navigateBack({trigger: false});
                        }
                    }.bind(this)
                }, function () {
                    this.confirmLeaveOut = false;
                    callback.call(context);
                }.bind(this));
            } else {
                callback.call(context);
            }
        },

        execute: function (callback, args, name) {
            this.checkConfirmLeaveOut(function () {
                Backbone.Router.prototype.execute.call(this, callback, args, name);
            }, null, true);
        },

        navigate: function (fragment, options) {
            this.history.push(fragment);
            return Backbone.Router.prototype.navigate.call(this, fragment, options);
        },

        navigateBack: function (options) {
            var url;
            if (this.history.length > 1) {
                url = this.history[this.history.length - 2];
            } else {
                url = this.history[0];
            }
            this.navigate(url, options);
        },

        _parseOptionsParams: function (string) {
            if (!string) {
                return {};
            }

            if (string.indexOf('&') === -1 && string.indexOf('=') === -1) {
                return string;
            }

            var options = {};
            if (typeof string !== 'undefined') {
                string.split('&').forEach(function (item, i) {
                    var p = item.split('=');
                    options[p[0]] = true;
                    if (p.length > 1) {
                        options[p[0]] = p[1];
                    }
                });
            }
            return options;
        },

        record: function (controller, action, id, options) {
            var options = this._parseOptionsParams(options);
            options.id = id;
            this.dispatch(controller, action, options);
        },

        view: function (controller, id, options) {
            this.record(controller, 'view', id, options);
        },

        edit: function (controller, id, options) {
            this.record(controller, 'edit', id, options);
        },

        create: function (controller, options) {
            this.record(controller, 'create', null, options);
        },

        action: function (controller, action, options) {
            this.dispatch(controller, action, this._parseOptionsParams(options));
        },

        defaultAction: function (controller) {
            this.dispatch(controller, null);
        },

        home: function () {
            this.dispatch('Home', null);
        },

        logout: function () {
            this.dispatch(null, 'logout');
            this.navigate('', {trigger: false});
        },

        clearCache: function () {
            this.dispatch(null, 'clearCache');
        },

        dispatch: function (controller, action, options) {
            var o = {
                controller: controller,
                action: action,
                options: options
            }
            this._last = o;
            this.trigger('routed', o);
        },

        getLast: function () {
            return this._last;
        }
    });

    return Router;

});

function isIOS9UIWebView() {
    var userAgent = window.navigator.userAgent;
    return /(iPhone|iPad|iPod).* OS 9_\d/.test(userAgent) && !/Version\/9\./.test(userAgent);
}

//override the backbone.history.loadUrl() and backbone.history.navigate()
//to fix the navigation issue (location.hash not change immediately) on iOS9
if (isIOS9UIWebView()) {
    Backbone.history.loadUrl = function (fragment, oldHash) {
        fragment = this.fragment = this.getFragment(fragment);
        return _.any(this.handlers, function (handler) {
            if (handler.route.test(fragment)) {
                function runCallback() {
                    handler.callback(fragment);
                }

                function wait() {
                    if (oldHash === location.hash) {
                        window.setTimeout(wait, 50);
                    } else {
                        runCallback();
                    }
                }
                wait();
                return true;
            }
        });
    };

    Backbone.history.navigate =
    // Attempt to load the current URL fragment. If a route succeeds with a
    // match, returns `true`. If no defined routes matches the fragment,
    // returns `false`.
    function (fragment, options) {
        var pathStripper = /#.*$/;
        if (!Backbone.History.started) return false;
        if (!options || options === true) options = { trigger: !!options };

        var url = this.root + '#' + (fragment = this.getFragment(fragment || ''));

        // Strip the hash for matching.
        fragment = fragment.replace(pathStripper, '');

        if (this.fragment === fragment) return;
        this.fragment = fragment;

        // Don't include a trailing slash on the root.
        if (fragment === '' && url !== '/') url = url.slice(0, -1);
        var oldHash = location.hash;
        // If pushState is available, we use it to set the fragment as a real URL.
        if (this._hasPushState) {
            this.history[options.replace ? 'replaceState' : 'pushState']({}, document.title, url);

            // If hash changes haven't been explicitly disabled, update the hash
            // fragment to store history.
        } else if (this._wantsHashChange) {
            this._updateHash(this.location, fragment, options.replace);
            if (this.iframe && (fragment !== this.getFragment(this.getHash(this.iframe)))) {
                // Opening and closing the iframe tricks IE7 and earlier to push a
                // history entry on hash-tag change.  When replace is true, we don't
                // want this.
                if (!options.replace) this.iframe.document.open().close();
                this._updateHash(this.iframe.location, fragment, options.replace);
            }

            // If you've told us that you explicitly don't want fallback hashchange-
            // based history, then `navigate` becomes a page refresh.
        } else {
            return this.location.assign(url);
        }

        if (options.trigger) return this.loadUrl(fragment, oldHash);
    }
}
