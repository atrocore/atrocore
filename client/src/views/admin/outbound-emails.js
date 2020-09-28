

Espo.define('views/admin/outbound-emails', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        layoutName: 'outboundEmails',

        dependencyDefs: {
            'smtpAuth': {
                map: {
                    true: [
                        {
                            action: 'show',
                            fields: ['smtpUsername', 'smtpPassword']
                        }
                    ]
                },
                default: [
                    {
                        action: 'hide',
                        fields: ['smtpUsername', 'smtpPassword']
                    }
                ]
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            var smtpSecurityField = this.getFieldView('smtpSecurity');
            this.listenTo(smtpSecurityField, 'change', function () {
                var smtpSecurity = smtpSecurityField.fetch()['smtpSecurity'];
                if (smtpSecurity == 'SSL') {
                    this.model.set('smtpPort', '465');
                } else if (smtpSecurity == 'TLS') {
                    this.model.set('smtpPort', '587');
                } else {
                    this.model.set('smtpPort', '25');
                }
            }.bind(this));
        },

    });

});

