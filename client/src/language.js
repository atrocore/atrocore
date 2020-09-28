

Espo.define('language', ['ajax'], function (Ajax) {

    var Language = function (cache) {
        this.cache = cache || null;
        this.data = {};
        this.name = 'default';
    };

    _.extend(Language.prototype, {

        data: null,

        cache: null,

        url: 'I18n',

        has: function (name, category, scope) {
            if (scope in this.data) {
                if (category in this.data[scope]) {
                    if (name in this.data[scope][category]) {
                        return true;
                    }
                }
            }
        },

        get: function (scope, category, name) {
            if (scope in this.data) {
                if (category in this.data[scope]) {
                    if (name in this.data[scope][category]) {
                        return this.data[scope][category][name];
                    }
                }
            }
            if (scope == 'Global') {
                return name;
            }
            return false;
        },

        translate: function (name, category, scope) {
            scope = scope || 'Global';
            category = category || 'labels';
            var res = this.get(scope, category, name);
            if (res === false && scope != 'Global') {
                res = this.get('Global', category, name);
            }
            return res;
        },

        translateOption: function (value, field, scope) {
            var translation = this.translate(field, 'options', scope);
            if (typeof translation != 'object') {
                translation = {};
            }
            return translation[value] || value;
        },

        loadFromCache: function (loadDefault) {
            var name = this.name;
            if (loadDefault) {
                name = 'default';
            }
            if (this.cache) {
                var cached = this.cache.get('app', 'language-' + name);
                if (cached) {
                    this.data = cached;
                    return true;
                }
            }
            return null;
        },

        clearCache: function () {
            if (this.cache) {
                this.cache.clear('app', 'language-' + this.name);
            }
        },

        storeToCache: function (loadDefault) {
            var name = this.name;
            if (loadDefault) {
                name = 'default';
            }
            if (this.cache) {
                this.cache.set('app', 'language-' + name, this.data);
            }
        },

        load: function (callback, disableCache, loadDefault) {
            this.once('sync', callback);

            if (!disableCache) {
                if (this.loadFromCache(loadDefault)) {
                    this.trigger('sync');
                    return;
                }
            }

            this.fetch(disableCache, loadDefault);
        },

        fetch: function (disableCache, loadDefault) {
            Ajax.getRequest(this.url, {default: loadDefault}).then(function (data) {
                this.data = data;
                if (!disableCache) {
                    this.storeToCache(loadDefault);
                }
                this.trigger('sync');
            }.bind(this));
        },

        sortFieldList: function (scope, fieldList) {
            return fieldList.sort(function (v1, v2) {
                 return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
            }.bind(this));
        },

        sortEntityList: function (entityList, plural) {
            var category = 'scopeNames';
            if (plural) {
                category += 'Plural';
            }
            return entityList.sort(function (v1, v2) {
                 return this.translate(v1, category).localeCompare(this.translate(v2, category));
            }.bind(this));
        }

    }, Backbone.Events);

    return Language;

});
