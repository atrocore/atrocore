


Espo.define('views/outbound-email/modals/test-send', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'test-send',

        _template: '<label class="control-label">{{translate \'Email Address\' scope=\'Email\'}}</label><input type="text" name="emailAddress" value="{{emailAddress}}" class="form-control">',

        data: function () {
            return {
                emailAddress: this.options.emailAddress,
            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'send',
                    text: this.translate('Send', 'labels', 'Email'),
                    style: 'primary',
                    onClick: function (dialog) {
                        var emailAddress = this.$el.find('input').val();
                        if (emailAddress == '') {
                            return;
                        }
                        this.trigger('send', emailAddress);
                    }.bind(this)
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: function (dialog) {
                        dialog.close();
                    }
                }
            ];

        },
    });
});


