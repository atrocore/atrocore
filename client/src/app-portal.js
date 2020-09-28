

Espo.define('app-portal', ['app', 'acl-portal-manager'], function (Dep, AclPortalManager) {

    return Dep.extend({

        aclName: 'aclPortal',

        masterView: 'views/site-portal/master',

        createAclManager: function () {
            return new AclPortalManager(this.user, null, this.settings.get('aclAllowDeleteCreated'));
        },

    });

});

