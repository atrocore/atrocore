

Espo.define('controllers/external-account', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: 'list',

        list: function (options) {
            this.collectionFactory.create('ExternalAccount', function (collection) {
                collection.once('sync', function () {
                    this.main('ExternalAccount.Index', {
                        collection: collection,
                    });
                }, this);
                collection.fetch();
            }, this);
        },

        edit: function (options) {
            var id = options.id;

            this.collectionFactory.create('ExternalAccount', function (collection) {
                collection.once('sync', function () {
                    this.main('ExternalAccount.Index', {
                        collection: collection,
                        id: id
                    });
                }, this);
                collection.fetch();
            }, this);
        },
    });
});
