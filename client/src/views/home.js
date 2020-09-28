

Espo.define('views/home', 'view', function (Dep) {

    return Dep.extend({

        template: 'home',

        setup: function () {
            this.createView('dashboard', 'views/dashboard', {
                el: this.options.el + ' > .home-content'
            });
        }
    });
});

