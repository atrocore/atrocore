

Espo.define('treo-core:views/site/header', 'class-replace!treo-core:views/site/header', function (Dep) {

    return Dep.extend({

        title: 'TreoCRM',

        setup: function () {
            this.navbarView = this.getMetadata().get('app.clientDefs.navbarView') || this.navbarView;

            Dep.prototype.setup.call(this);
        }

    });

});


