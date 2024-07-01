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

Espo.define('views/user/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        sideView: 'views/user/record/detail-side',

        editModeDisabled: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupNonAdminFieldsAccess();

            if (this.model.id == this.getUser().id || this.getUser().isAdmin()) {
                this.buttonList.push({
                    name: 'access',
                    label: 'Access',
                    style: 'default'
                });

                if (!this.getConfig().get('resetPasswordViaEmailOnly', false)) {
                    this.dropdownItemList.push({
                        name: 'changePassword',
                        label: this.translate('Change Password', 'labels', 'User'),
                        style: 'default'
                    });
                }

                this.dropdownItemList.push({
                    name: 'resetPassword',
                    label: this.translate('Reset Password', 'labels', 'User'),
                    style: 'default'
                });
            }

            if (this.model.id == this.getUser().id) {
                this.listenTo(this.model, 'after:save', function () {
                    this.getUser().set(this.model.toJSON());
                }.bind(this));
            }

            this.setupFieldAppearance();
        },

        setupNonAdminFieldsAccess: function () {
            if (this.getUser().isAdmin()) return;

            var nonAdminReadOnlyFieldList = [
                'userName',
                'isActive',
                'isAdmin',
                'teams',
                'roles',
                'password',
                'account'
            ];

            nonAdminReadOnlyFieldList.forEach(function (field) {
                this.setFieldReadOnly(field, true);
            }, this);

            if (!this.getAcl().checkScope('Team')) {
                this.setFieldReadOnly('defaultTeam', true);
            }
        },

        setupFieldAppearance: function () {
            this.controlFieldAppearance();
            this.listenTo(this.model, 'change', function () {
                this.controlFieldAppearance();
            }, this);
        },

        controlFieldAppearance: function () {
            this.showField('isAdmin');
            this.showField('roles');
            this.showField('teams');
            this.showField('defaultTeam');
            this.hideField('account');
            this.showField('title');
        },

        actionChangePassword: function () {
            this.notify('Loading...');

            this.createView('changePassword', 'views/modals/change-password', {
                userId: this.model.id
            }, function (view) {
                view.render();
                this.notify(false);

                this.listenToOnce(view, 'changed', function () {
                    setTimeout(function () {
                        this.getBaseController().logout();
                    }.bind(this), 2000);
                }, this);

            }.bind(this));
        },

        actionResetPassword() {
            $.ajax({
                url: 'User/action/resetPassword',
                type: 'POST',
                data: JSON.stringify({
                    userId: this.model.id
                })
            }).done(function () {
                Espo.Ui.success(this.translate('uniqueLinkHasBeenSent', 'messages', 'User'));
                setTimeout(() => {
                    if (this.model.id === this.getUser().id) {
                        this.getBaseController().logout();
                    }
                }, 2000);
            }.bind(this));
        },

        actionPreferences: function () {
            this.getRouter().navigate('#Preferences/edit/' + this.model.id, {trigger: true});
        },

        actionAccess: function () {
            this.notify('Loading...');

            $.ajax({
                url: 'User/action/acl',
                type: 'GET',
                data: {
                    id: this.model.id,
                }
            }).done(function (aclData) {
                this.createView('access', 'views/user/modals/access', {
                    aclData: aclData,
                    model: this.model,
                }, function (view) {
                    this.notify(false);
                    view.render();
                }.bind(this));
            }.bind(this));
        },

        getGridLayout: function (callback) {
            this._helper.layoutManager.get(this.model.name, this.options.layoutName || this.layoutName, function (simpleLayout) {
                var layout = Espo.Utils.cloneDeep(simpleLayout);

                layout.push({
                    "label": "Teams and Access Control",
                    "name": "accessControl",
                    "rows": [
                        [{"name":"isActive"}, {"name":"isAdmin"}],
                        [{"name":"teams"}, false],
                        [{"name":"roles"}, {"name":"defaultTeam"}]
                    ]
                });

                var gridLayout = {
                    type: 'record',
                    layout: this.convertDetailLayout(layout),
                };

                callback(gridLayout);
            }.bind(this));
        }
    });

});
