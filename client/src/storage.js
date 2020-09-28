

Espo.define('storage', [], function () {

    var Storage = function () {
    };

    _.extend(Storage.prototype, {

        prefix: 'espo',

        storageObject: localStorage,

        composeFullPrefix: function (type) {
            return this.prefix + '-' + type;
        },

        composeKey: function (type, name) {
            return this.composeFullPrefix(type) + '-' + name;
        },

        checkType: function (type) {
            if (typeof type === 'undefined' && toString.call(type) != '[object String]' || type == 'cache') {
                throw new TypeError("Bad type \"" + type + "\" passed to Espo.Storage.");
            }
        },

        has: function (type, name) {
            this.checkType(type);
            var key = this.composeKey(type, name);

            return this.storageObject.getItem(key) !== null;
        },

        get: function (type, name) {
            this.checkType(type);

            var key = this.composeKey(type, name);

            try {
                var stored = this.storageObject.getItem(key);
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
                } else if (stored[0] == "{" || stored[0] == "[") { // for backward compatibility
                    try {
                        result = JSON.parse(stored);
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
                this.storageObject.setItem(key, value);
            } catch (error) {
                console.error(error);
                return null;
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
                reText = '^' + this.prefix + '-';
            }
            var re = new RegExp(reText);
            for (var i in this.storageObject) {
                if (re.test(i)) {
                    delete this.storageObject[i];
                }
            }
        }
    });

    Storage.extend = Backbone.Router.extend;

    return Storage;
});
