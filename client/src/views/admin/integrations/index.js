

Espo.define('views/admin/integrations/index', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/integrations/index',

        integrationList: null,

        integration: null,

        data: function () {
            return {
                integrationList: this.integrationList,
                integration: this.integration,
            };
        },

        events: {
            'click #integrations-menu a.integration-link': function (e) {
                var name = $(e.currentTarget).data('name');
                this.openIntegration(name);
            },
        },

        setup: function () {
            this.integrationList = Object.keys(this.getMetadata().get('integrations') || {});;

            this.integration = this.options.integration || null;

            this.on('after:render', function () {
                this.renderHeader();
                if (!this.integration) {
                    this.renderDefaultPage();
                } else {
                    this.openIntegration(this.integration);
                }
            });
        },

        openIntegration: function (integration) {
            this.integration = integration;

            this.getRouter().navigate('#Admin/integrations/name=' + integration, {trigger: false});

            var viewName = this.getMetadata().get('integrations.' + integration + '.view') || 'views/admin/integrations/' + Espo.Utils.camelCaseToHyphen(this.getMetadata().get('integrations.' + integration + '.authMethod'));
            this.notify('Loading...');
            this.createView('content', viewName, {
                el: '#integration-content',
                integration: integration,
            }, function (view) {
                this.renderHeader();
                view.render();
                this.notify(false);
                $(window).scrollTop(0);
            }.bind(this));
        },

        renderDefaultPage: function () {
            $('#integration-header').html('').hide();
            if (this.integrationList.length) {
            	var msg = this.translate('selectIntegration', 'messages', 'Integration');
            } else {
            	var msg = '<p class="lead">' + this.translate('noIntegrations', 'messages', 'Integration') + '</p>';
            }
            $('#integration-content').html(msg);
        },

        renderHeader: function () {
            if (!this.integration) {
                $('#integration-header').html('');
                return;
            }
            $('#integration-header').show().html(this.translate(this.integration, 'titles', 'Integration'));
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Integrations', 'labels', 'Admin'));
        },
    });
});


