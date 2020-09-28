

Espo.define('views/email-account/record/edit', ['views/record/edit', 'views/email-account/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            Detail.prototype.setupFieldsBehaviour.call(this);
            Detail.prototype.initSslFieldListening.call(this);
            Detail.prototype.initSmtpFieldsControl.call(this);

            if (this.getUser().isAdmin()) {
                this.setFieldNotReadOnly('assignedUser');
            } else {
                this.setFieldReadOnly('assignedUser');
            }
        },

        setupFieldsBehaviour: function () {
            Detail.prototype.setupFieldsBehaviour.call(this);
        },

        controlStatusField: function () {
            Detail.prototype.controlStatusField.call(this);
        },

        controlSmtpFields: function () {
            Detail.prototype.controlSmtpFields.call(this);
        },

        controlSmtpAuthField: function () {
            Detail.prototype.controlSmtpAuthField.call(this);
        },

        wasFetched: function () {
            Detail.prototype.wasFetched.call(this);
        }

    });
});
