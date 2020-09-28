

Espo.define('views/admin/notifications', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        layoutName: 'notifications',

        dependencyDefs: {
            'assignmentEmailNotifications': {
                map: {
                    true: [
                        {
                            action: 'show',
                            fields: ['assignmentEmailNotificationsEntityList']
                        }
                    ]
                },
                default: [
                    {
                        action: 'hide',
                        fields: ['assignmentEmailNotificationsEntityList']
                    }
                ]
            },
            'adminNotifications': {
                map: {
                    true: [
                        {
                            action: 'show',
                            fields: ['adminNotificationsNewVersion', 'adminNotificationsNewExtensionVersion']
                        }
                    ]
                },
                default: [
                    {
                        action: 'hide',
                        fields: ['adminNotificationsNewVersion', 'adminNotificationsNewExtensionVersion']
                    }
                ]
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.controlStreamEmailNotificationsEntityList();
            this.listenTo(this.model, 'change', function (model) {
                if (model.hasChanged('streamEmailNotifications') || model.hasChanged('portalStreamEmailNotifications')) {
                    this.controlStreamEmailNotificationsEntityList();
                }
            }, this);
        },

        controlStreamEmailNotificationsEntityList: function () {
            if (this.model.get('streamEmailNotifications') || this.model.get('portalStreamEmailNotifications')) {
                this.showField('streamEmailNotificationsEntityList');
                this.showField('streamEmailNotificationsTypeList');
            } else {
                this.hideField('streamEmailNotificationsEntityList');
                this.hideField('streamEmailNotificationsTypeList');
            }
        }

    });

});

