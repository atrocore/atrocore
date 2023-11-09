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

Espo.define('collection', [], function () {

    var Collection = Backbone.Collection.extend({

        name: null,

        total: 0,

        offset: 0,

        maxSize: 20,

        sortBy: 'id',

        asc: false,

        where: null,

        whereAdditional: null,

        lengthCorrection: 0,

        _user: null,

        initialize: function (models, options) {
            options = options || {};
            this.name = options.name || this.name;
            this.urlRoot = this.urlRoot || this.name;
            this.url = this.url || this.urlRoot;

            this.sortBy = options.sortBy || this.sortBy;
            this.defaultSortBy = this.sortBy;
            this.asc = ('asc' in options) ? options.asc : this.asc;
            this.defaultAsc = this.asc;
            this.data = {};

            Backbone.Collection.prototype.initialize.call(this);
        },

        _onModelEvent: function (event, model, collection, options) {
            if (event === 'sync' && collection !== this) return;
            Backbone.Collection.prototype._onModelEvent.apply(this, arguments);
        },

        reset: function (models, options) {
            this.lengthCorrection = 0;
            Backbone.Collection.prototype.reset.call(this, models, options);
        },

        remove: function (element, options) {
            const removed = Backbone.Collection.prototype.remove.call(this, element, options);
            if (this.total > 0) {
                this.total -= _.isArray(removed) ? removed.length : 1
                this.trigger('update-total', this, options);
            }
        },

        sort: function (field, asc) {
            this.sortBy = field;
            this.asc = asc;
            this.fetch();
        },

        nextPage: function () {
            var offset = this.offset + this.maxSize;
            this.setOffset(offset);
        },

        previousPage: function () {
            var offset = this.offset - this.maxSize;
            this.setOffset(offset);
        },

        firstPage: function () {
            this.setOffset(0);
        },

        lastPage: function () {
            var offset = this.total - this.total % this.maxSize;
            this.setOffset(offset);
        },

        setOffset: function (offset) {
            if (offset < 0) {
                throw new RangeError('offset can not be less than 0');
            }
            if (offset > this.total) {
                throw new RangeError('offset can not be larger than total count');
            }
            this.offset = offset;
            this.fetch();
        },

        parse: function (response) {
            this.total = response.total;

            if ('additionalData' in response) {
                this.dataAdditional = response.additionalData;
            } else {
                this.dataAdditional = null;
            }

            return response.list;
        },

        fetch: function (options) {
            var options = options || {};
            options.data = _.extend(options.data || {}, this.data);

            this.offset = options.offset || this.offset;
            this.sortBy = options.sortBy || this.sortBy;
            this.asc = options.asc || this.asc;
            this.where = options.where || this.where;

            if (!('maxSize' in options)) {
                options.data.maxSize = options.more ? this.maxSize : ((this.length > this.maxSize) ? this.length : this.maxSize);
            } else {
                options.data.maxSize = options.maxSize;
            }

            options.data.offset = options.more ? this.length + this.lengthCorrection : this.offset;
            options.data.sortBy = this.sortBy;
            options.data.asc = this.asc;
            options.data.where = this.getWhere();

            this.lastXhr = Backbone.Collection.prototype.fetch.call(this, options);

            return this.lastXhr;
        },

        abortLastFetch: function () {
            if (this.lastXhr && this.lastXhr.readyState < 4) {
                this.lastXhr.abort();
            }
        },

        getWhere: function () {
            return (this.where || []).concat(this.whereAdditional || []);
        },

        getUser: function () {
            return this._user;
        },

        getEntityType: function () {
            return this.name;
        }

    });

    return Collection;

});
