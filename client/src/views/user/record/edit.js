

Espo.define('views/user/record/edit', ['views/record/edit', 'views/user/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        sideView: 'views/user/record/edit-side',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupNonAdminFieldsAccess();

            if (this.model.id == this.getUser().id) {
                this.listenTo(this.model, 'after:save', function () {
                    this.getUser().set(this.model.toJSON());
                }, this);
            }

            this.hideField('sendAccessInfo');

            var passwordChanged = false;

            this.listenToOnce(this.model, 'change:password', function (model) {
                passwordChanged = true;
                if (model.get('emailAddress')) {
                    this.showField('sendAccessInfo');
                    this.model.set('sendAccessInfo', true);
                }
            }, this);

            this.listenTo(this.model, 'change:emailAddress', function (model) {
                if (passwordChanged) {
                    if (model.get('emailAddress')) {
                        this.showField('sendAccessInfo');
                        this.model.set('sendAccessInfo', true);
                    } else {
                        this.hideField('sendAccessInfo');
                        this.model.set('sendAccessInfo', false);
                    }
                }
            }, this);

            Detail.prototype.setupFieldAppearance.call(this);

            this.hideField('passwordPreview');
            this.listenTo(this.model, 'change:passwordPreview', function (model, value) {
                if (value.length) {
                    this.showField('passwordPreview');
                } else {
                    this.hideField('passwordPreview');
                }
            }, this);
        },

        setupNonAdminFieldsAccess: function () {
            Detail.prototype.setupNonAdminFieldsAccess.call(this);
        },

        controlFieldAppearance: function () {
            Detail.prototype.controlFieldAppearance.call(this);
        },

        getGridLayout: function (callback) {
            this._helper.layoutManager.get(this.model.name, this.options.layoutName || this.layoutName, function (simpleLayout) {
                var layout = Espo.Utils.cloneDeep(simpleLayout);

                layout.push({
                    "label": "Teams and Access Control",
                    "name": "accessControl",
                    "rows": [
                        [{"name":"isActive"}, {"name":"isAdmin"}],
                        [{"name":"teams"}, {"name":"isPortalUser"}],
                        [{"name":"roles"}, {"name":"defaultTeam"}]
                    ]
                });
                layout.push({
                    "label": "Portal",
                    "name": "portal",
                    "rows": [
                        [{"name":"portals"}, {"name":"contact"}],
                        [{"name":"portalRoles"}, {"name":"accounts"}]
                    ]
                });

                if (this.type == 'edit' && this.getUser().isAdmin()) {
                    layout.push({
                        label: 'Password',
                        rows: [
                            [
                                {
                                    name: 'password',
                                    type: 'password',
                                    params: {
                                        required: this.isNew,
                                        readyToChange: true
                                    }
                                },
                                {
                                    name: 'generatePassword',
                                    view: 'views/user/fields/generate-password',
                                    customLabel: ''
                                }
                            ],
                            [
                                {
                                    name: 'passwordConfirm',
                                    type: 'password',
                                    params: {
                                        required: this.isNew,
                                        readyToChange: true
                                    }
                                },
                                {
                                    name: 'passwordPreview',
                                    view: 'views/fields/base',
                                    params: {
                                        readOnly: true
                                    }
                                }
                            ],
                            [
                                {
                                    name: 'sendAccessInfo'
                                },
                                {
                                    name: 'passwordInfo',
                                    customLabel: '',
                                    customCode: this.getPasswordSendingMessage()
                                }

                            ]
                        ]
                    });
                }

                var gridLayout = {
                    type: 'record',
                    layout: this.convertDetailLayout(layout),
                };

                callback(gridLayout);
            }.bind(this));
        },

        getPasswordSendingMessage: function () {
            if (this.getConfig().get('smtpServer') && this.getConfig().get('smtpServer') !== '') {
                return '';
            }
            return this.translate('setupSmtpBefore', 'messages', 'User').replace('{url}', '#Admin/outboundEmails');
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            if (!this.isNew) {
                if ('password' in data) {
                    if (data['password'] == '') {
                        delete data['password'];
                        delete data['passwordConfirm'];
                        this.model.unset('password');
                        this.model.unset('passwordConfirm');
                    }
                }
            }

            return data;
        },

        errorHandlerUserNameExists: function () {
            Espo.Ui.error(this.translate('userNameExists', 'messages', 'User'))
        }

    });

});
