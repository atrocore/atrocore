

Espo.define('views/inbound-email/fields/email-address', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.on('change', function () {
                var emailAddress = this.model.get('emailAddress');
                this.model.set('name', emailAddress);
                if (this.model.isNew() || !this.model.get('replyToAddress')) {
                    this.model.set('replyToAddress', emailAddress);
                }
            }, this);
        },

    });
});
