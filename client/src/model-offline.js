


Espo.define('model-offline', 'model', function (Model) {

    var ModelOffline = Model.extend({

        name: null,

        cache: null,

        _key: null,

        initialize: function (attributes, options) {
            options = options || {};
            Model.prototype.initialize.apply(this, arguments);
            this._key = this.url = this.name;
            this.cache = options.cache || null;
        },

        load: function (callback, disableCache, sync) {
            this.once('sync', callback);

            if (!disableCache) {
                if (this.loadFromCache()) {
                    this.trigger('sync');
                    return;
                }
            }

            this.fetch({
                async: !(sync || false)
            });
        },

        loadFromCache: function () {
            if (this.cache) {
                var cached = this.cache.get('app', this._key);
                if (cached) {
                    this.set(cached);
                    return true;
                }
            }
            return null;
        },

        storeToCache: function () {
            if (this.cache) {
                this.cache.set('app', this._key, this.toJSON());
            }
        },

        isNew: function () {
            return false;
        }

    });

    return ModelOffline;

});

