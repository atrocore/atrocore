

Espo.define('views/import/list', 'views/list', function (Dep) {

    return Dep.extend({

        createButton: false,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.menu.buttons.unshift({
                html: 'New Import',
                link: '#Import',
                style: 'primary',
                acl: 'edit'
            });
        }

    });
});
