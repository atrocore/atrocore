

Espo.define('session-storage', 'storage', function (Dep) {

    return Dep.extend({

        storageObject: sessionStorage,

        get: function (name) {
            try {
                var stored = this.storageObject.getItem(name);
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

        set: function (name, value) {
            if (value instanceof Object || Array.isArray(value)) {
                value = '__JSON__:' + JSON.stringify(value);
            }
            try {
                this.storageObject.setItem(name, value);
            } catch (error) {
                console.error(error);
            }
        },

        clear: function (name) {
            for (var i in this.storageObject) {
                if (i === name) {
                    delete this.storageObject[i];
                }
            }
        }

    });
});
