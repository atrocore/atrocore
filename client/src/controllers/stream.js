
Espo.define('controllers/stream', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: 'index',

        index: function () {
            this.main('views/stream', {
                displayTitle: true,
            }, function (view) {
                view.render();
            });
        },

        posts: function () {
            this.main('views/stream', {
                displayTitle: true,
                filter: 'posts',
            }, function (view) {
                view.render();
            });
        },

        updates: function () {
            this.main('views/stream', {
                displayTitle: true,
                filter: 'updates',
            }, function (view) {
                view.render();
            });
        },

    });

});
