

Espo.define('treo-core:views/admin/notifications', 'class-replace!treo-core:views/admin/notifications',
    Dep => Dep.extend({

        revertFields: ['notificationNewModuleVersionDisabled'],

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
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.revertFieldsInConfig();
            this.listenTo(this.model, 'change', () => {
                this.revertFieldsInConfig();
            });
        },

        revertFieldsInConfig() {
            this.revertFields.forEach(field => {
                this.getConfig().set({[field]: !this.model.get(field)});
            });
        }

    })
);

