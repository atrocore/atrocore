

Espo.define('views/settings/edit', 'views/edit', function (Dep) {

    return Dep.extend({

        scope: 'Settings',

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main > .header',
                template: this.options.headerTemplate
            });
        }

    });
});

