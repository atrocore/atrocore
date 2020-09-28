

Espo.define('treo-core:controllers/composer', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: "list",

        list: function () {
            this.collectionFactory.create('Composer', function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;
                collection.sortBy = 'name';
                collection.asc = false;

                this.main('treo-core:views/composer/list', {
                    scope: 'Composer',
                    collection: collection
                });
            }, this);
        },
    });
});
