
Espo.define('controllers/dashboard', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: 'index',

        index: function () {
            this.main('views/dashboard', {
                displayTitle: true,
            }, function (view) {
                view.render();
            });
        }

    });

});
