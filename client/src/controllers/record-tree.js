
Espo.define('controllers/record-tree', 'controllers/record', function (Dep) {

    return Dep.extend({

        defaultAction: 'listTree',

        beforeView: function (options) {
            Dep.prototype.beforeView.call(this, options);
            options = options || {};
            if (options.model) {
                options.model.unset('childCollection');
                options.model.unset('childList');
            }
        },

        beforeListTree: function () {
            this.handleCheckAccess('read');
        },

        listTree: function (options) {
            this.getCollection(function (collection) {
                collection.url = collection.name + '/action/listTree';
                this.main(this.getViewName('listTree'), {
                    scope: this.name,
                    collection: collection
                });
            });
        }

    });

});
