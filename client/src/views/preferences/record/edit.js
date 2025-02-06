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

Espo.define('views/preferences/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        sideView: null,

        hideNotificationPanel: true,

        buttonList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel',
            }
        ],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addButton({
                name: 'reset',
                html: this.getLanguage().translate('Reset to Default', 'labels', 'Admin'),
                style: 'danger'
            });


            if (this.model.id == this.getUser().id) {
                this.on('after:save', function () {
                    var data = this.model.toJSON();
                    this.getPreferences().set(data);
                    this.getPreferences().trigger('update');
                }, this);
            }

            this.listenTo(this.model, 'after:save', function () {
                if (
                    this.model.get('localeId') !== this.attributes.language
                    ||
                    this.model.get('styleId') !== this.attributes.styleId
                ) {
                    window.location.reload();
                }
            }, this);

            this.listenTo(this.model, 'change:receiveNotifications', () => {
                if(this.model.get('receiveNotifications')){
                    this.setFieldRequired('notificationProfileId')
                }else{
                    this.setFieldNotRequired('notificationProfileId')
                }
            })
        },

        actionReset: function () {
            this.confirm(this.translate('resetPreferencesConfirmation', 'messages'), function () {
                $.ajax({
                    url: 'Preferences/' + this.model.id,
                    type: 'DELETE',
                }).done(function (data) {
                    Espo.Ui.success(this.translate('resetPreferencesDone', 'messages'));
                    this.model.set(data);
                    for (var attribute in data) {
                        this.setInitalAttributeValue(attribute, data[attribute]);
                    }
                    this.getPreferences().set(this.model.toJSON());
                    this.getPreferences().trigger('update');
                }.bind(this));
            }, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if(this.model.get('receiveNotifications')){
                this.setFieldRequired('notificationProfileId')
            }else{
                this.setFieldNotRequired('notificationProfileId')
            }
        },

        exit: function (after) {
            if (after === 'cancel') {
                this.getRouter().navigate('#User/view/' + this.model.id, {trigger: true});
            }
        },

    });

});
