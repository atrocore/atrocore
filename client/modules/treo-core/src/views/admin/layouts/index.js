

Espo.define('treo-core:views/admin/layouts/index', 'class-replace!treo-core:views/admin/layouts/index',
    Dep => Dep.extend({

        template: 'treo-core:admin/layouts/index',

        renderLayoutHeader: function () {
            if (!this.scope) {
                $("#layout-header").html("");
                return;
            }
            $("#layout-header").show().html(this.getLanguage().translate(this.scope, 'scopeNamesPlural') + " &raquo; " + this.getLanguage().translate(this.type, 'layouts', 'Admin'));
        }

    })
);


