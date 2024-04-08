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

Espo.define('model', [], function () {

    var Dep = Backbone.Model;

    var Model = Dep.extend({

        name: null,

        dateTime: null,

        _user: null,

        defs: null,

        defaults: {},

        initialize: function () {
            this.urlRoot = this.urlRoot || this.name;

            this.defs = this.defs || {};
            this.defs.fields = this.defs.fields || {};
            this.defs.links = this.defs.links || {};
            this.defaults = {}
            Dep.prototype.initialize.call(this);
        },

        sync: function (method, model, options) {
            if (method === 'patch') options.type = 'PUT';
            return Dep.prototype.sync.call(this, method, model, options);
        },

        set: function (key, val, options) {
            if (typeof key === 'object') {
                var o = key;
                if (this.idAttribute in o) {
                    this.id = o[this.idAttribute];
                }
            } else if (key === 'id') {
                this.id = val;
            }
            return Dep.prototype.set.call(this, key, val, options);
        },

        get: function (key) {
            if (key === 'id' && this.id) {
                return this.id;
            }
            return Dep.prototype.get.call(this, key);
        },

        has: function (key) {
            var value = this.get(key);
            return (typeof value !== 'undefined');
        },

        isNew: function () {
            return !this.id;
        },

        setDefs: function (defs) {
            this.defs = defs || {};
            this.defs.fields = this.defs.fields || {};
        },

        getClonedAttributes: function () {
            var attributes = {};
            for (var name in this.attributes) {
                attributes[name] = Espo.Utils.cloneDeep(this.attributes[name]);
            }
            return attributes;
        },

        populateDefaults: function () {
            var defaultHash = {};

            let seed = false;

            if ('fields' in this.defs) {
                for (let field in this.defs.fields) {
                    const defaultValue = this.getFieldParam(field, 'default')
                    if (defaultValue && defaultValue.includes('{{') && defaultValue.includes('}}')) {
                        seed = $.ajax({
                            url: this.name + '/action/Seed?silent=true',
                            type: 'GET',
                            dataType: 'json',
                            async: false,
                        }).responseJSON
                        break;
                    }
                }
            }

            if ('fields' in this.defs) {
                for (var field in this.defs.fields) {
                    var defaultValue = this.getFieldParam(field, 'default');

                    if (seed && seed[field] != null) {
                        defaultValue = seed[field]
                    }

                    if (defaultValue != null && !this.getFieldParam(field, 'required') && this.getFieldParam(field, 'setDefaultOnlyIfRequired')) {
                        this.defaults[field] = defaultValue
                        defaultValue = null
                    }

                    if (defaultValue != null) {
                        var defaultValue = this.parseDefaultValue(defaultValue);
                        defaultHash[field] = defaultValue;
                    }

                    var defaultAttributes = this.getFieldParam(field, 'defaultAttributes');
                    if (defaultAttributes) {
                        for (var attribute in defaultAttributes) {
                            defaultHash[attribute] = defaultAttributes[attribute];
                        }
                    }
                }
            }

            defaultHash = Espo.Utils.cloneDeep(defaultHash);
            for (var attr in defaultHash) {
                if (this.has(attr)) {
                    delete defaultHash[attr];
                }
            }

            this.set(defaultHash, {silent: true});
        },

        parseDefaultValue: function (defaultValue) {
            if (typeof defaultValue == 'string' && defaultValue.indexOf('javascript:') === 0) {
                var code = defaultValue.substring(11);
                defaultValue = (new Function("with(this) { " + code + "}")).call(this);
            }
            return defaultValue;
        },

        getLinkMultipleColumn: function (field, column, id) {
            return ((this.get(field + 'Columns') || {})[id] || {})[column];
        },

        setRelate: function (data) {

            var setRelate = function (options) {
                var link = options.link;
                var model = options.model;
                if (!link || !model) {
                    throw new Error('Bad related options');
                }
                var type = this.defs.links[link].type;
                switch (type) {
                    case 'belongsToParent':
                        this.set(link + 'Id', model.id);
                        this.set(link + 'Type', model.name);
                        this.set(link + 'Name', model.get('name'));
                        break;
                    case 'belongsTo':
                        this.set(link + 'Id', model.id);
                        this.set(link + 'Name', model.get('name'));
                        break;
                    case 'hasMany':
                        var ids = [];
                        ids.push(model.id);
                        var names = {};
                        names[model.id] = model.get('name');
                        this.set(link + 'Ids', ids);
                        this.set(link + 'Names', names);
                        break;
                }
            }.bind(this);

            if (Object.prototype.toString.call(data) === '[object Array]') {
                data.forEach(function (options) {
                    setRelate(options);
                }.bind(this));
            } else {
                setRelate(data);
            }
        },

        getFieldType: function (field) {
            if (('defs' in this) && ('fields' in this.defs) && (field in this.defs.fields)) {
                return this.defs.fields[field].type || null;
            }
            return null;
        },

        getFieldParam: function (field, param) {
            if (('defs' in this) && ('fields' in this.defs) && (field in this.defs.fields)) {
                if (param in this.defs.fields[field]) {
                    return this.defs.fields[field][param];
                }
            }
            return null;
        },

        getLinkType: function (link) {
            if (('defs' in this) && ('links' in this.defs) && (link in this.defs.links)) {
                return this.defs.links[link].type || null;
            }
            return null;
        },

        getLinkParam: function (link, param) {
            if (('defs' in this) && ('links' in this.defs) && (link in this.defs.links)) {
                if (param in this.defs.links[link]) {
                    return this.defs.links[link][param];
                }
            }
            return null;
        },

        isFieldReadOnly: function (field) {
            return this.getFieldParam(field, 'readOnly') || false;
        },

        isRequired: function (field) {
            return this.getFieldParam(field, 'required') || false;
        },

        getLinkMultipleIdList: function (field) {
            return this.get(field + 'Ids') || [];
        },

        getTeamIdList: function () {
            return this.get('teamsIds') || [];
        },

        getDateTime: function () {
            return this.dateTime;
        },

        getUser: function () {
            return this._user;
        },

        hasField: function (field) {
            return ('defs' in this) && ('fields' in this.defs) && (field in this.defs.fields);
        },

        hasLink: function (link) {
            return ('defs' in this) && ('links' in this.defs) && (link in this.defs.links);
        },

        isEditable: function () {
            return true;
        },

        isRemovable: function () {
            return true;
        },

        getEntityType: function () {
            return this.name;
        },

        fetch: function (options) {
            this.lastXhr = Dep.prototype.fetch.call(this, options);
            return this.lastXhr;
        },

        abortLastFetch: function () {
            if (this.lastXhr && this.lastXhr.readyState < 4) {
                this.lastXhr.abort();
            }
        }

    });

    return Model;

});
