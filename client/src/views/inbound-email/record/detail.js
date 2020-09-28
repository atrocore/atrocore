

Espo.define('views/inbound-email/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupFieldsBehaviour();
            this.initSslFieldListening();
        },


        wasFetched: function () {
            if (!this.model.isNew()) {
                return !!((this.model.get('fetchData') || {}).lastUID);
            }
            return false;
        },

        initSmtpFieldsControl: function () {
            this.controlSmtpFields();
            this.controlSentFolderField();
            this.listenTo(this.model, 'change:useSmtp', this.controlSmtpFields, this);
            this.listenTo(this.model, 'change:smtpAuth', this.controlSmtpAuthField, this);
            this.listenTo(this.model, 'change:storeSentEmails', this.controlSentFolderField, this);
        },

        controlSmtpFields: function () {
            if (this.model.get('useSmtp')) {
                this.showField('smtpHost');
                this.showField('smtpPort');
                this.showField('smtpAuth');
                this.showField('smtpSecurity');
                this.showField('smtpTestSend');
                this.showField('fromName');
                this.showField('smtpIsShared');
                this.showField('smtpIsForMassEmail');
                this.showField('storeSentEmails');

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
                this.hideField('fromName');
                this.hideField('smtpIsShared');
                this.hideField('smtpIsForMassEmail');
                this.hideField('storeSentEmails');
                this.hideField('sentFolder');

                this.setFieldNotRequired('smtpHost');
                this.setFieldNotRequired('smtpPort');
                this.setFieldNotRequired('smtpUsername');
            }
        },

        controlSentFolderField: function () {
            if (this.model.get('useSmtp') && this.model.get('storeSentEmails')) {
                this.showField('sentFolder');
                this.setFieldRequired('sentFolder');
            } else {
                this.hideField('sentFolder');
                this.setFieldNotRequired('sentFolder');
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

            this.initSmtpFieldsControl();

            var handleRequirement = function (model) {
                if (model.get('createCase')) {
                    this.showField('caseDistribution');
                } else {
                    this.hideField('caseDistribution');
                }

                if (model.get('createCase') && ['Round-Robin', 'Least-Busy'].indexOf(model.get('caseDistribution')) != -1) {
                    this.setFieldRequired('team');
                    this.showField('targetUserPosition');
                } else {
                    this.setFieldNotRequired('team');
                    this.hideField('targetUserPosition');
                }
                if (model.get('createCase') && 'Direct-Assignment' === model.get('caseDistribution')) {
                    this.setFieldRequired('assignToUser');
                    this.showField('assignToUser');
                } else {
                    this.setFieldNotRequired('assignToUser');
                    this.hideField('assignToUser');
                }
                if (model.get('createCase') && model.get('createCase') !== '') {
                    this.showField('team');
                } else {
                    this.hideField('team');
                }
            }.bind(this);

            this.listenTo(this.model, 'change:createCase', function (model, value, o) {
                handleRequirement(model);

                if (!o.ui) return;

                if (!model.get('createCase')) {
                    this.model.set({
                        caseDistribution: '',
                        teamId: null,
                        teamName: null,
                        assignToUserId: null,
                        assignToUserName: null,
                        targetUserPosition: ''
                    });
                }
            }, this);

            handleRequirement(this.model);

            this.listenTo(this.model, 'change:caseDistribution', function (model, value, o) {
                handleRequirement(model);

                if (!o.ui) return;

                setTimeout(function () {
                    if (!this.model.get('caseDistribution')) {
                        this.model.set({
                            assignToUserId: null,
                            assignToUserName: null,
                            targetUserPosition: ''
                        });
                    } else if (this.model.get('caseDistribution') === 'Direct-Assignment') {
                        this.model.set({
                            targetUserPosition: ''
                        });
                    } else {
                        this.model.set({
                            assignToUserId: null,
                            assignToUserName: null
                        });
                    }
                }.bind(this), 10);
            });
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
        }
    });
});
