

Espo.define('views/email-account/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupFieldsBehaviour();
            this.initSslFieldListening();
            this.initSmtpFieldsControl();

            if (this.getUser().isAdmin()) {
                this.setFieldNotReadOnly('assignedUser');
            } else {
                this.setFieldReadOnly('assignedUser');
            }
        },

        setupFieldsBehaviour: function () {
            this.controlStatusField();
            this.listenTo(this.model, 'change:status', function (model, value, o) {
                if (o.ui) {
                    this.controlStatusField();
                }
            }, this);
            this.listenTo(this.model, 'change:useImap', function (model, value, o) {
                if (o.ui) {
                    this.controlStatusField();
                }
            }, this);

            if (this.wasFetched()) {
                this.setFieldReadOnly('fetchSince');
            } else {
                this.setFieldNotReadOnly('fetchSince');
            }
        },

        controlStatusField: function () {
            var list = ['username', 'port', 'host', 'monitoredFolders'];
            if (this.model.get('status') === 'Active' && this.model.get('useImap')) {
                list.forEach(function (item) {
                    this.setFieldRequired(item);
                }, this);
            } else {
                list.forEach(function (item) {
                    this.setFieldNotRequired(item);
                }, this);
            }
        },

        wasFetched: function () {
            if (!this.model.isNew()) {
                return !!((this.model.get('fetchData') || {}).lastUID);
            }
            return false;
        },

        initSslFieldListening: function () {
            this.listenTo(this.model, 'change:ssl', function (model, value, o) {
                if (o.ui) {
                    if (value) {
                        this.model.set('port', 993);
                    } else {
                        this.model.set('port', 143);
                    }
                }
            }, this);

            this.listenTo(this.model, 'change:smtpSecurity', function (model, value, o) {
                if (o.ui) {
                    if (value === 'SSL') {
                        this.model.set('smtpPort', 465);
                    } else if (value === 'TLS') {
                        this.model.set('smtpPort', 587);
                    } else {
                        this.model.set('smtpPort', 25);
                    }
                }
            }, this);
        },

        initSmtpFieldsControl: function () {
            this.controlSmtpFields();
            this.listenTo(this.model, 'change:useSmtp', this.controlSmtpFields, this);
            this.listenTo(this.model, 'change:smtpAuth', this.controlSmtpAuthField, this);
        },

        controlSmtpFields: function () {
            if (this.model.get('useSmtp')) {
                this.showField('smtpHost');
                this.showField('smtpPort');
                this.showField('smtpAuth');
                this.showField('smtpSecurity');
                this.showField('smtpTestSend');

                this.setFieldRequired('smtpHost');
                this.setFieldRequired('smtpPort');

                this.controlSmtpAuthField();
            } else {
                this.hideField('smtpHost');
                this.hideField('smtpPort');
                this.hideField('smtpAuth');
                this.hideField('smtpUsername');
                this.hideField('smtpPassword');
                this.hideField('smtpSecurity');
                this.hideField('smtpTestSend');

                this.setFieldNotRequired('smtpHost');
                this.setFieldNotRequired('smtpPort');
                this.setFieldNotRequired('smtpUsername');
            }
        },

        controlSmtpAuthField: function () {
            if (this.model.get('smtpAuth')) {
                this.showField('smtpUsername');
                this.showField('smtpPassword');
                this.setFieldRequired('smtpUsername');
            } else {
                this.hideField('smtpUsername');
                this.hideField('smtpPassword');
                this.setFieldNotRequired('smtpUsername');
            }
        },

    });

});

