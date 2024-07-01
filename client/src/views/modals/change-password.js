/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

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

                if (this.getUser().get('isAdmin') && this.getUser().id !== this.options.userId) {
                    this.createView('sendAccessInfo', 'views/fields/bool', {
                        model: user,
                        mode: 'edit',
                        el: this.options.el + ' .field[data-name="sendAccessInfo"]',
                        defs: {
                            name: 'sendAccessInfo',
                            params: {
                                default: false
                            }
                        }
                    });

                    this.createView('generatePassword', 'views/user/fields/generate-password', {
                        model: user,
                        mode: 'edit',
                        el: this.options.el + ' .field[data-name="generatePassword"]',
                        defs: {
                            name: 'generatePassword',
                            customLabel: ''
                        }
                    });

                    this.createView('passwordPreview', 'views/fields/base', {
                        model: user,
                        mode: 'edit',
                        readOnly: true,
                        el: this.options.el + ' .field[data-name="passwordPreview"]',
                        defs: {
                            name: 'passwordPreview',
                        }
                    });
                }

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
                    userId: this.options.userId,
                    currentPassword: this.model.get('currentPassword'),
                    password: this.model.get('password'),
                    sendAccessInfo: this.model.get('sendAccessInfo') ?? false
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

