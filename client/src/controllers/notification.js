
Espo.define('controllers/notification', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: 'index',

        index: function () {
            this.main('views/notification/list', {
            }, function (view) {
                view.render();
            });
        }

    });

});
