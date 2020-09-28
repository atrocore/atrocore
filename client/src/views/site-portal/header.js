

Espo.define('views/site-portal/header', 'views/site/header', function (Dep) {

    return Dep.extend({

        template: 'site/header',

        navbarView: 'views/site-portal/navbar'

    });

});


