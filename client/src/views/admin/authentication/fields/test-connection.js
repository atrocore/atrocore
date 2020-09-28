

Espo.define('views/admin/authentication/fields/test-connection', 'views/fields/base', function (Dep) {

    return Dep.extend({

        _template: '<button class="btn btn-default" data-action="testConnection">{{translate \'Test Connection\' scope=\'Settings\'}}</button>',

        events: {
            'click [data-action="testConnection"]': function () {
                this.testConnection();
            },
        },

        fetch: function () {
            return {};
        },

        getConnectionData: function () {
            var data = {
                'host': this.model.get('ldapHost'),
                'port': this.model.get('ldapPort'),
                'useSsl': this.model.get('ldapSecurity'),
                'useStartTls': this.model.get('ldapSecurity'),
                'username': this.model.get('ldapUsername'),
                'password': this.model.get('ldapPassword'),
                'bindRequiresDn': this.model.get('ldapBindRequiresDn'),
                'accountDomainName': this.model.get('ldapAccountDomainName'),
                'accountDomainNameShort': this.model.get('ldapAccountDomainNameShort'),
                'accountCanonicalForm': this.model.get('ldapAccountCanonicalForm')
            };
            return data;
        },

        testConnection: function () {
            var data = this.getConnectionData();

            this.$el.find('button').prop('disabled', true);

            this.notify('Connecting', null, null, 'Settings');

            $.ajax({
                url: 'Settings/action/testLdapConnection',
                type: 'POST',
                data: JSON.stringify(data),
                error: function (xhr, status) {
                    var statusReason = xhr.getResponseHeader('X-Status-Reason') || '';
                    statusReason = statusReason.replace(/ $/, '');
                    statusReason = statusReason.replace(/,$/, '');

                    var msg = this.translate('Error') + ' ' + xhr.status;
                    if (statusReason) {
                        msg += ': ' + statusReason;
                    }
                    Espo.Ui.error(msg);
                    console.error(msg);
                    xhr.errorIsHandled = true;

                    this.$el.find('button').prop('disabled', false);
                }.bind(this)
            }).done(function () {
                this.$el.find('button').prop('disabled', false);
                Espo.Ui.success(this.translate('ldapTestConnection', 'messages', 'Settings'));
            }.bind(this));

        },

    });

});

