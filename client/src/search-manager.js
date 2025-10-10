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

Espo.define('search-manager', [], function () {

    var SearchManager = function (collection, type, storage, dateTime, defaultData, emptyOnReset) {

        this.collection = collection;
        this.scope = collection?.name;
        this.storage = storage;
        this.type = type || 'list';
        this.dateTime = dateTime;
        this.emptyOnReset = emptyOnReset;
        this.savedSearchList = [];
        this.isFilterSetValue = null;
        this.additionalBoolFilterList = [];

        this.emptyData = {
            textFilter: '',
            bool: {},
            advanced: {},
            queryBuilder: [],
            primary: null,
            pinned: {},
            savedFilters: [],
            queryBuilderApplied: false
        };

        if (defaultData) {
            this.defaultData = defaultData;
            for (var p in this.emptyData) {
                if (!(p in defaultData)) {
                    defaultData[p] = Espo.Utils.clone(this.emptyData[p]);
                }
            }
        }

        this.data = Espo.Utils.clone(defaultData) || this.emptyData;

        this.sanitizeData();
    };

    _.extend(SearchManager.prototype, {

        data: null,

        sanitizeData: function () {
            if (!('advanced' in this.data)) {
                this.data.advanced = {};
            }
            if (!('queryBuilder' in this.data)) {
                this.data.queryBuilder = [];
            }
            if (!('bool' in this.data)) {
                this.data.bool = {};
            }
            if (!('textFilter' in this.data)) {
                this.data.textFilter = '';
            }
            if (!('pinned' in this.data)) {
                this.data.pinned = {};
            }

            if (!('savedFilters' in this.data)) {
                this.data.savedFilters = [];
            }

            if (!('queryBuilderApplied' in this.data)) {
                this.data.queryBuilderApplied = false;
            }
        },

        getWhere: function () {
            var where = [];

            if (this.data.textFilter && this.data.textFilter != '') {
                where.push({
                    type: 'textFilter',
                    value: this.data.textFilter
                });
            }

            if (this.data.bool) {
                var o = {
                    type: 'bool',
                    value: [],
                    data: {}
                };
                for (var name in this.data.bool) {
                    if (this.data.bool[name]) {
                        o.value.push(name);
                        var boolData = this.data.boolData;
                        if (boolData && boolData[name]) {
                            o.data[name] = boolData[name];
                        }
                        if(this.boolFilterData && this.boolFilterData[name]) {
                            o.data[name] = this.boolFilterData[name]
                        }
                    }
                }
                if (o.value.length) {
                    where.push(o);
                }
            }

            if (this.data.primary) {
                var o = {
                    type: 'primary',
                    value: this.data.primary,
                };
                if (o.value.length) {
                    where.push(o);
                }
            }

            if (this.data.savedFilters && this.data.savedFilters.length) {
                this.data.savedFilters.forEach(item => {
                    if (item?.data?.condition) {
                        where.push(item.data);
                    } else {
                        where = where.concat(this.getAdvancedWhere(item.data))
                    }
                });
            }

            if (this.data.queryBuilder.condition && this.isQueryBuilderApplied()) {
                where.push(Espo.Utils.clone(this.data.queryBuilder));
            }

            // to remove when switching to querybuilder everywhere
            if (this.data.advanced && this.isQueryBuilderApplied()) {
                where = where.concat(this.getAdvancedWhere(this.data.advanced))
            }

            return where;
        },

        getWherePart: function (name, defs) {
            var attribute = name;

            if ('where' in defs) {
                return defs.where;
            } else {
                var type = defs.type;

                if (type == 'or' || type == 'and') {
                    var a = [];
                    var value = defs.value || {};
                    for (var n in value) {
                        a.push(this.getWherePart(n, value[n]));
                    }
                    return {
                        type: type,
                        value: a
                    };
                }
                if ('field' in defs) { // for backward compatibility
                    attribute = defs.field;
                }
                if ('attribute' in defs) {
                    attribute = defs.attribute;
                }
                if (defs.dateTime) {
                    return {
                        type: type,
                        attribute: attribute,
                        value: defs.value,
                        dateTime: true,
                        timeZone: this.dateTime.timeZone || 'UTC'
                    };
                } else {
                    value = defs.value;
                    return {
                        type: type,
                        attribute: attribute,
                        value: value
                    };
                }
            }
        },

        loadStored: function () {
            this.data = this.storage.get(this.type + 'QueryBuilder', this.scope) || Espo.Utils.clone(this.defaultData) || Espo.Utils.clone(this.emptyData);
            this.sanitizeData();
            this.isFilterSetValue = this.isFilterSet()
            return this;
        },

        get: function () {
            return Espo.Utils.clone(this.data);
        },

        getQueryBuilder: function () {
            return Espo.Utils.clone(this.data.queryBuilder)
        },

        isQueryBuilderApplied: function () {
            return !!this.data.queryBuilderApplied;
        },

        getBool: function () {
            return  Espo.Utils.clone(this.data.bool);
        },

        getSavedFilters: function () {
            return Espo.Utils.clone(this.data.savedFilters);
        },

        geTextFilter: function () {
            return this.data.textFilter;
        },

        setAdvanced: function (advanced) {
            this.data = Espo.Utils.clone(this.data);
            this.data.advanced = advanced;
        },

        setQueryBuilder: function (queryBuilder) {
            this.data = Espo.Utils.clone(this.data);
            this.data.queryBuilder = queryBuilder;
        },

        setBool: function (bool) {
            this.data = Espo.Utils.clone(this.data);
            this.data.bool = bool;
        },

        setPrimary: function (primary) {
            this.data = Espo.Utils.clone(this.data);
            this.data.primary = primary;
        },

        set: function (data) {
            this.data = data;
            this.refreshIsFilterSet();
            if (this.storage) {
                this.storage.set(this.type + 'QueryBuilder', this.scope, data);
            }
        },

        empty: function () {
            this.data = Espo.Utils.clone(this.emptyData);
            if (this.storage) {
                this.storage.clear(this.type + 'QueryBuilder', this.scope);
            }
        },

        reset: function () {
            if (this.emptyOnReset) {
                this.empty();
                return;
            }
            this.data = Espo.Utils.clone(this.defaultData) || Espo.Utils.clone(this.emptyData);
            if (this.storage) {
                this.storage.clear(this.type + 'QueryBuilder', this.scope);
            }
            this.refreshIsFilterSet();
        },

        update: function (newData) {
            this.set({...this.data, ...newData});
        },

        fetchCollection: function() {
            if(!this.collection) {
                return;
            }
            this.collection.reset();

            if(this.mandatoryBoolFilterList) {
                let bool = {};
                for (const filter of this.mandatoryBoolFilterList) {
                    bool[filter] = true;
                }
                this.setBool({
                    ...this.getBool(),
                    ...bool
                });
            }

            this.collection.where = this.getWhere();
            this.collection.abortLastFetch();
            this.collection.fetch().then(() => window.Backbone.trigger('after:search', this.collection));
        },

        refreshIsFilterSet() {
            this.isFilterSetValue = this.isFilterSet();
            if(this.collection) {
                this.collection.trigger('filter-state:changed', this.isFilterSetValue);
                window.Backbone.trigger('filter-state:changed', this.collection)
            }
        },

        isFilterSet() {
            let filterIsSet = this.data.savedFilters.length > 0 ;
            if(filterIsSet) {
                return true;
            }

            let queryBuilder = this.data.queryBuilder;

            if(this.data.queryBuilderApplied) {
                filterIsSet = queryBuilder.condition && Array.isArray(queryBuilder.rules) && queryBuilder.rules.length > 0;
                if(filterIsSet) {
                    return true;
                }
            }

            let bool = this.data.bool;

            for (const boolKey in bool) {
                if(Array.isArray(this.mandatoryBoolFilterList) && this.mandatoryBoolFilterList.includes(boolKey)){
                    continue;
                }
                if(bool[boolKey]){
                    return true;
                }
            }
            return false;
        },

        isTextFilterSet() {
          return !!this.data.textFilter
        },

        getDateTimeWhere: function (type, field, value) {
            var where = {
                field: field
            };
            if (!value && ~['on', 'before', 'after'].indexOf(type)) {
                return null;
            }

            switch (type) {
                case 'today':
                    where.type = 'between';
                    var start = this.dateTime.getNowMoment().startOf('day').utc();

                    var from = start.format(this.dateTime.internalDateTimeFormat);
                    var to = start.add(1, 'days').format(this.dateTime.internalDateTimeFormat);
                    where.value = [from, to];
                    break;
                case 'past':
                    where.type = 'before';
                    where.value = this.dateTime.getNowMoment().utc().format(this.dateTime.internalDateTimeFormat);
                    break;
                case 'future':
                    where.type = 'after';
                    where.value = this.dateTime.getNowMoment().utc().format(this.dateTime.internalDateTimeFormat);
                    break;
                case 'on':
                    where.type = 'between';
                    var start = moment(value, this.dateTime.internalDateFormat, this.timeZone).utc();

                    var from = start.format(this.dateTime.internalDateTimeFormat);
                    var to = start.add(1, 'days').format(this.dateTime.internalDateTimeFormat);

                    where.value = [from, to];
                    break;
                case 'before':
                    where.type = 'before';
                    where.value = moment(value, this.dateTime.internalDateFormat, this.timeZone).utc().format(this.dateTime.internalDateTimeFormat);
                    break;
                case 'after':
                    where.type = 'after';
                    where.value = moment(value, this.dateTime.internalDateFormat, this.timeZone).utc().format(this.dateTime.internalDateTimeFormat);
                    break;
                case 'between':
                    where.type = 'between';
                    if (value[0] && value[1]) {
                        var from = moment(value[0], this.dateTime.internalDateFormat, this.timeZone).utc().format(this.dateTime.internalDateTimeFormat);
                        var to = moment(value[1], this.dateTime.internalDateFormat, this.timeZone).utc().format(this.dateTime.internalDateTimeFormat);
                        where.value = [from, to];
                    }
                    break;
                default:
                    where.type = type;
            }

            return where;
        }
    });

    return SearchManager;

});
