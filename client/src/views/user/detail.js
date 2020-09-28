

Espo.define('views/user/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.id == this.getUser().id || this.getUser().isAdmin()) {
                this.menu.buttons.push({
                    name: 'preferences',
                    label: 'Preferences',
                    style: 'default',
                    action: "preferences",
                    link: '#Preferences/edit/' + this.getUser().id
                });

                if (!this.model.get('isPortalUser')) {
                    if ((this.getAcl().check('EmailAccountScope') && this.model.id == this.getUser().id) || this.getUser().isAdmin()) {
                        this.menu.buttons.push({
                            name: 'emailAccounts',
                            label: "Email Accounts",
                            style: 'default',
                            action: "emailAccounts",
                            link: '#EmailAccount/list/userId=' + this.model.id + '&userName=' + encodeURIComponent(this.model.get('name'))
                        });
                    }

                    if (this.model.id == this.getUser().id && this.getAcl().checkScope('ExternalAccount')) {
                        this.menu.buttons.push({
                            name: 'externalAccounts',
                            label: 'External Accounts',
                            style: 'default',
                            action: "externalAccounts",
                            link: '#ExternalAccount'
                        });
                    }
                }
            }
        },

        actionPreferences: function () {
            this.getRouter().navigate('#Preferences/edit/' + this.model.id, {trigger: true});
        },

        actionEmailAccounts: function () {
            this.getRouter().navigate('#EmailAccount/list/userId=' + this.model.id + '&userName=' + encodeURIComponent(this.model.get('name')), {trigger: true});
        },

        actionExternalAccounts: function () {
            this.getRouter().navigate('#ExternalAccount', {trigger: true});
        },
    });
});

