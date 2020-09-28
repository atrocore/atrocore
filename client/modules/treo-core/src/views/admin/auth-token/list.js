

Espo.define('treo-core:views/admin/auth-token/list', 'class-replace!treo-core:views/admin/auth-token/list',
    Dep => Dep.extend({

        getHeader: function () {
            return  `<a href="#Admin">${this.translate('Administration')}</a>` +
                    `<span class="subsection">${this.translate('Users', 'labels', 'Admin')}</span>` +
                    this.getLanguage().translate('Auth Tokens', 'labels', 'Admin');
        }

    })
);

