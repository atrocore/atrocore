

Espo.define('views/modals/change-password', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'change-password',

        template: 'modals/change-password',

        setup: function () {
            this.buttonList = [
                {
                    name: 'change',
                    label: 'Change',
                    style: 'danger'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.header = this.translate('Change Password', 'labels', 'User');

            this.wait(true);

            this.getModelFactory().create('User', function (user) {
                this.model = user;

                this.createView('currentPassword', 'views/fields/password', {
                    model: user,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="currentPassword"]',
                    defs: {
                        name: 'currentPassword',
                        params: {
                            required: true,
                        }
                    }
                });

                this.createView('password', 'views/fields/password', {
                    model: user,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="password"]',
                    defs: {
                        name: 'password',
                        params: {
                            required: true,
                        }
                    }
                });
                this.createView('passwordConfirm', 'views/fields/password', {
                    model: user,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="passwordConfirm"]',
                    defs: {
                        name: 'passwordConfirm',
                        params: {
                            required: true,
                        }
                    }
                });

                this.wait(false);
            }, this);

        },


        actionChange: function () {
            this.getView('currentPassword').fetchToModel();
            this.getView('password').fetchToModel();
            this.getView('passwordConfirm').fetchToModel();

            var notValid = this.getView('currentPassword').validate() ||
                           this.getView('password').validate() ||
                           this.getView('passwordConfirm').validate();

            if (notValid) {
                return;
            }

            this.$el.find('button[data-name="change"]').addClass('disabled');

            $.ajax({
                url: 'User/action/changeOwnPassword',
                type: 'POST',
                data: JSON.stringify({
                    currentPassword: this.model.get('currentPassword'),
                    password: this.model.get('password')
                }),
                error: function () {
                    this.$el.find('button[data-name="change"]').removeClass('disabled');
                }.bind(this)
            }).done(function () {
                Espo.Ui.success(this.translate('passwordChanged', 'messages', 'User'));
                this.trigger('changed');
                this.close();
            }.bind(this));
        },

    });
});

