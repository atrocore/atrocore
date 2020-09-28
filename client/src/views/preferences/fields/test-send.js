

Espo.define('views/preferences/fields/test-send', 'views/outbound-email/fields/test-send', function (Dep) {

    return Dep.extend({

        checkAvailability: function () {
            if (this.model.get('smtpServer')) {
                this.$el.find('button').removeClass('hidden');
            } else {
                this.$el.find('button').addClass('hidden');
            }
        },

        afterRender: function () {
            this.checkAvailability();

            this.stopListening(this.model, 'change:smtpServer');
            this.listenTo(this.model, 'change:smtpServer', function () {
                this.checkAvailability();
            }, this);
        },

        getSmtpData: function () {
            var data = {
                'server': this.model.get('smtpServer'),
                'port': this.model.get('smtpPort'),
                'auth': this.model.get('smtpAuth'),
                'security': this.model.get('smtpSecurity'),
                'username': this.model.get('smtpUsername'),
                'password': this.model.get('smtpPassword') || null,
                'fromName': this.getUser().get('name'),
                'fromAddress': this.getUser().get('emailAddress'),
                'type': 'preferences',
                'id': this.model.id
            };
            return data;
        },


    });

});

