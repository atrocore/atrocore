

Espo.define('views/inbound-email/fields/test-send', 'views/email-account/fields/test-send', function (Dep) {

    return Dep.extend({

        getSmtpData: function () {
            var data = {
                'server': this.model.get('smtpHost'),
                'port': this.model.get('smtpPort'),
                'auth': this.model.get('smtpAuth'),
                'security': this.model.get('smtpSecurity'),
                'username': this.model.get('smtpUsername'),
                'password': this.model.get('smtpPassword') || null,
                'fromName': this.model.get('fromName'),
                'fromAddress': this.model.get('emailAddress'),
                'type': 'inboundEmail',
                'id': this.model.id
            };
            return data;
        }

     });

});
