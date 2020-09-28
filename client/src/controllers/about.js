
Espo.define('controllers/about', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: 'about',

        about: function () {
            this.main('About', {}, function (view) {
                view.render();
            });
        }
    });

});
