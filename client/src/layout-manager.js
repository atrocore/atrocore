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

Espo.define('layout-manager', [], function () {

    var LayoutManager = function (options, userId) {
        var options = options || {};
        this.cache = options.cache || null;
        this.applicationId = options.applicationId || 'default-id';
        this.data = {};
        this.ajax = $.ajax;
        this.userId = userId;
    }

    _.extend(LayoutManager.prototype, {

        cache: null,

        data: null,

        getKey: function (scope, type, layoutProfileId) {
            let key;
            if (this.userId) {
                key = this.applicationId + '-' + this.userId + '-' + scope + '-' + type;
            }
            key = this.applicationId + '-' + scope + '-' + type;
            if (layoutProfileId) {
                key += '-' + layoutProfileId
            }
            return key
        },

        getUrl: function (scope, type, layoutProfileId) {
            return scope + '/layout/' + type + "?isAdminPage=" + (window.location.hash.search("#Admin") === 0) + (layoutProfileId ? ('&layoutProfileId=' + layoutProfileId) : '');
        },

        get: function (scope, type, layoutProfileId, callback, cache) {
            if (typeof cache == 'undefined') {
                cache = true;
            }
            if (typeof layoutProfileId == 'function') {
                callback = layoutProfileId
                layoutProfileId = null
            }

            var key = this.getKey(scope, type, layoutProfileId);

            if (cache) {
                if (key in this.data) {
                    if (typeof callback === 'function') {
                        callback(this.data[key]);
                    }
                    return;
                }
            }

            if (this.cache && cache) {
                var cached = this.cache.get('app-layout', key);
                if (cached) {
                    if (typeof callback === 'function') {
                        callback(cached);
                    }
                    this.data[key] = cached;
                    return;
                }
            }

            this.ajax({
                url: this.getUrl(scope, type, layoutProfileId),
                type: 'GET',
                dataType: 'json',
                success: function (layout) {
                    if (typeof callback === 'function') {
                        callback(layout);
                    }
                    this.data[key] = layout;
                    if (this.cache) {
                        this.cache.set('app-layout', key, layout);
                    }
                }.bind(this)
            });
        },

        set: function (scope, type, layoutProfileId, layout, callback) {
            var key = this.getKey(scope, type, layoutProfileId);

            this.ajax({
                url: this.getUrl(scope, type, layoutProfileId),
                type: 'PUT',
                data: JSON.stringify(layout),
                success: function () {
                    if (this.cache && key) {
                        this.cache.set('app-layout', key, layout);
                    }
                    this.data[key] = layout;
                    this.trigger('sync');
                    if (typeof callback === 'function') {
                        callback();
                    }
                }.bind(this)
            });
        },

        resetToDefault: function (scope, type, layoutProfileId, callback) {
            var key = this.getKey(scope, type, layoutProfileId);

            Espo.Ui.notify('Saving...');
            this.ajax({
                url: 'Layout/action/resetToDefault',
                type: 'POST',
                data: JSON.stringify({
                    scope: scope,
                    name: type,
                    layoutProfileId: layoutProfileId
                }),
                success: function (layout) {
                    if (this.cache) {
                        this.cache.clear('app-layout', key);
                    }
                    this.data[key] = layout;
                    this.trigger('sync');
                    if (typeof callback === 'function') {
                        callback();
                    }
                    Espo.Ui.notify('Done', 'success', 1000 * 3);
                }.bind(this)
            });
        }

    }, Backbone.Events);

    return LayoutManager;

});


