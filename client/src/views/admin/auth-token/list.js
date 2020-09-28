

Espo.define('views/admin/auth-token/list', 'views/list', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.menu.buttons = [];
        },

        getHeader: function () {
            return '<a href="#Admin">' + this.translate('Administration') + "</a> » " + this.getLanguage().translate('Auth Tokens', 'labels', 'Admin');
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Auth Tokens', 'labels', 'Admin'));
        },
    });
});

