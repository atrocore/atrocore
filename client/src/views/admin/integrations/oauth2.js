

Espo.define('views/admin/integrations/oauth2', 'views/admin/integrations/edit', function (Dep) {

    return Dep.extend({

        template: 'admin/integrations/oauth2',

        data: function () {

            return _.extend({
                // TODO fetch from server
                redirectUri: this.getConfig().get('siteUrl') + '?entryPoint=oauthCallback'
            }, Dep.prototype.data.call(this));
        },

    });

});
