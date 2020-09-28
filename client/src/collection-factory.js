

 Espo.define('collection-factory', [], function () {

    var CollectionFactory = function (loader, modelFactory) {
        this.loader = loader;
        this.modelFactory = modelFactory;
    };

    _.extend(CollectionFactory.prototype, {

        loader: null,

        modelFactory: null,

        create: function (name, callback, context) {
            context = context || this;

            this.modelFactory.getSeed(name, function (seed) {

                var asc = this.modelFactory.metadata.get('entityDefs.' + name + '.collection.asc');
                var sortBy = this.modelFactory.metadata.get('entityDefs.' + name + '.collection.sortBy');

                var className = this.modelFactory.metadata.get('clientDefs.' + name + '.collection') || 'Collection';

                Espo.loader.require(className, function (collectionClass) {
                    var collection = new collectionClass(null, {
                        name: name,
                        asc: asc,
                        sortBy: sortBy
                    });
                    collection.model = seed;
                    collection._user = this.modelFactory.user;
                    callback.call(context, collection);
                }.bind(this));
            }.bind(this));
        }
    });

    return CollectionFactory;

});

