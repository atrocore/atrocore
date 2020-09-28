
Espo.define('collections/note', 'collection', function (Dep) {

    return Dep.extend({

        fetchNew: function (options) {
            var options = options || {};
            options.data = options.data || {};

            if (this.length) {
                options.data.after = this.models[0].get('createdAt');
                options.remove = false;
                options.at = 0;
                options.maxSize = null;
            }

            this.fetch(options);
        },

    });

});
