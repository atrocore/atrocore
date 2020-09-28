

Espo.define('views/site/header', 'view', function (Dep) {

    return Dep.extend({

        template: 'site/header',

        title: 'EspoCRM',

        data: {
            title: this.title,
        },

        navbarView: 'views/site/navbar',

        setup: function () {
            this.createView('navbar', this.navbarView, {el: '#navbar', title: this.title});
        }

    });

});


