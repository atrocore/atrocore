

Espo.define('treo-core:views/admin/auth-log-record/list', 'class-replace!treo-core:views/admin/auth-log-record/list',
    Dep => Dep.extend({

        getHeader: function () {
            return '<a href="#Admin">' + this.translate('Administration') + "</a> &raquo; " + this.getLanguage().translate('Auth Log', 'labels', 'Admin');
        }

    })
);

