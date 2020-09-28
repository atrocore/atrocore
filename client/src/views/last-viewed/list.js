

Espo.define('views/last-viewed/list', 'views/list', function (Dep) {

    return Dep.extend({

        searchPanel: false,

        createButton: false,

        setup: function () {
            Dep.prototype.setup.call(this);
            this.collection.url = 'LastViewed';
        }

    });
});
