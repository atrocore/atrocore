

Espo.define('model-factory', [], function () {

    var ModelFactory = function (loader, metadata, user) {
        this.loader = loader;
        this.metadata = metadata;
        this.user = user;

        this.seeds = {};
    };

    _.extend(ModelFactory.prototype, {

        loader: null,

        metadata: null,

        seeds: null,

        dateTime: null,

        user: null,

        create: function (name, callback, context) {
            context = context || this;
            this.getSeed(name, function (seed) {
                var model = new seed();
                callback.call(context, model);
            }.bind(this));
        },

        getSeed: function (name, callback) {
            if ('name' in this.seeds) {
                callback(this.seeds[name]);
                return;
            }

            var className = this.metadata.get('clientDefs.' + name + '.model') || 'model';

            Espo.loader.require(className, function (modelClass) {
                this.seeds[name] = modelClass.extend({
                    name: name,
                    defs: this.metadata.get('entityDefs.' + name) || {},
                    dateTime: this.dateTime,
                    _user: this.user
                });
                callback(this.seeds[name]);
            }.bind(this));
        },
    });

    return ModelFactory;

});
