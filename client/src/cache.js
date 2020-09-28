

Espo.define('cache', [], function () {

    var Cache = function (cacheTimestamp) {
        this.basePrefix = this.prefix;
        if (cacheTimestamp) {
            this.prefix =  this.basePrefix + '-' + cacheTimestamp;
        }
        if (!this.get('app', 'timestamp')) {
            this.storeTimestamp();
        }
    };

    _.extend(Cache.prototype, {

        prefix: 'cache',

        handleActuality: function (cacheTimestamp) {
            var stored = parseInt(this.get('app', 'cacheTimestamp'));
            if (stored) {
                if (stored !== cacheTimestamp) {
                    this.clear();
                    this.set('app', 'cacheTimestamp', cacheTimestamp);
                    this.storeTimestamp();
                }
            } else {
                this.clear();
                this.set('app', 'cacheTimestamp', cacheTimestamp);
                this.storeTimestamp();
            }
        },

        storeTimestamp: function () {
            var frontendCacheTimestamp = Date.now();
            this.set('app', 'timestamp', frontendCacheTimestamp);
        },

        composeFullPrefix: function (type) {
            return this.prefix + '-' + type;
        },

        composeKey: function (type, name) {
            return this.composeFullPrefix(type) + '-' + name;
        },

        checkType: function (type) {
            if (typeof type === 'undefined' && toString.call(type) != '[object String]') {
                throw new TypeError("Bad type \"" + type + "\" passed to Cache().");
            }
        },

        get: function (type, name) {
            this.checkType(type);

            var key = this.composeKey(type, name);

            try {
                var stored = localStorage.getItem(key);
            } catch (error) {
                console.error(error);
                return null;
            }

            if (stored) {
                var result = stored;

                if (stored.length > 9 && stored.substr(0, 9) === '__JSON__:') {
                    var jsonString = stored.substr(9);
                    try {
                        result = JSON.parse(jsonString);
                    } catch (error) {
                        result = stored;
                    }
                }
                return result;
            }
            return null;
        },

        set: function (type, name, value) {
            this.checkType(type);
            var key = this.composeKey(type, name);
            if (value instanceof Object || Array.isArray(value)) {
                value = '__JSON__:' + JSON.stringify(value);
            }
            try {
                localStorage.setItem(key, value);
            } catch (error) {
                console.error(error);
            }
        },

        clear: function (type, name) {
            var reText;
            if (typeof type !== 'undefined') {
                if (typeof name === 'undefined') {
                    reText = '^' + this.composeFullPrefix(type);
                } else {
                    reText = '^' + this.composeKey(type, name);
                }
            } else {
                reText = '^' + this.basePrefix + '-';
            }
            var re = new RegExp(reText);
            for (var i in localStorage) {
                if (re.test(i)) {
                    delete localStorage[i];
                }
            }
        },

    });

    return Cache;

});
