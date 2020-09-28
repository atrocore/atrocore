
Espo.define('views/email/fields/compose-from-address', 'views/fields/base', function (Dep) {

    return Dep.extend({

        editTemplate: 'email/fields/compose-from-address/edit',

        data: function () {
            return _.extend({
                list: this.list,
                noSmtpMessage: this.translate('noSmtpSetup', 'messages', 'Email').replace('{link}', '<a href="#Preferences">'+this.translate('Preferences')+'</a>')
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.list = [];

            var primaryEmailAddress = this.getUser().get('emailAddress');
            if (primaryEmailAddress) {
                this.list.push(primaryEmailAddress);
            }

            var emailAddressList = this.getUser().get('emailAddressList') || [];
            emailAddressList.forEach(function (item) {
                this.list.push(item);
            }, this);

            this.list = _.uniq(this.list);

            if (this.getConfig().get('outboundEmailIsShared') && this.getConfig().get('outboundEmailFromAddress')) {
                var address = this.getConfig().get('outboundEmailFromAddress');
                if (!~this.list.indexOf(address)) {
                    this.list.push(this.getConfig().get('outboundEmailFromAddress'));
                }
            }
        },
    });

});
