

Espo.define('Views.ExternalAccount.Index', 'View', function (Dep) {

    return Dep.extend({

        template: 'external-account.index',

        data: function () {
            return {
                externalAccountList: this.externalAccountList,
                id: this.id,
                externalAccountListCount: this.externalAccountList.length
            };
        },

        events: {
            'click #external-account-menu a.external-account-link': function (e) {
                var id = $(e.currentTarget).data('id') + '__' + this.userId;
                this.openExternalAccount(id);
            },
        },

        setup: function () {
            this.externalAccountList = this.collection.toJSON();

            this.userId = this.getUser().id;
            this.id = this.options.id || null;
            if (this.id) {
                this.userId = this.id.split('__')[1];
            }

            this.on('after:render', function () {
                this.renderHeader();
                if (!this.id) {
                    this.renderDefaultPage();
                } else {
                    this.openExternalAccount(this.id);
                }
            });
        },

        openExternalAccount: function (id) {
            this.id = id;

            var integration = this.integration = id.split('__')[0];
            this.userId = id.split('__')[1];

            this.getRouter().navigate('#ExternalAccount/edit/' + id, {trigger: false});

            var viewName =
                    this.getMetadata().get('integrations.' + integration + '.userView') ||
                    'ExternalAccount.' + this.getMetadata().get('integrations.' + integration + '.authMethod');

            this.notify('Loading...');
            this.createView('content', viewName, {
                el: '#external-account-content',
                id: id,
                integration: integration
            }, function (view) {
                this.renderHeader();
                view.render();
                this.notify(false);
                $(window).scrollTop(0);
            }.bind(this));
        },

        renderDefaultPage: function () {
            $('#external-account-header').html('').hide();
            $('#external-account-content').html('');
        },

        renderHeader: function () {
            if (!this.id) {
                $('#external-account-header').html('');
                return;
            }
            $('#external-account-header').show().html(this.integration);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.translate('ExternalAccount', 'scopeNamesPlural'));
        },
    });
});


