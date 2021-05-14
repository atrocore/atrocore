/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

Espo.define('views/notification/badge', 'view', function (Dep) {

    return Dep.extend({

        template: 'notification/badge',

        notificationsCheckInterval: 1,

        timeout: null,

        intervalConditions: [],

        events: {
            'click a[data-action="showNotifications"]': function (e) {

                if (!this.hasView('panel')) {
                    this.showNotifications();
                } else {
                    this.closeNotifications();
                }

                setTimeout(function () {
                    this.checkUpdates();
                }.bind(this), 100);
            },
            'click a[data-action="close"]': function () {
                this.closeNotifications();
            }
        },

        setup: function () {
            this.intervalConditions = this.options.intervalConditions || this.intervalConditions;

            this.unreadCount = localStorage.getItem('unreadCount') || 0;

            this.once('remove', function () {
                if (this.timeout) {
                    clearTimeout(this.timeout);
                }
            }, this);

            this.notificationsCheckInterval = this.getConfig().get('notificationsCheckInterval') || this.notificationsCheckInterval;
        },

        afterRender: function () {
            this.$badge = this.$el.find('.notifications-button');
            this.$number = this.$el.find('.number-badge');

            this.runCheckUpdates(true);

            $('body').attr({'style': ''});
        },

        showNotRead: function (count) {
            this.$badge.attr('title', this.translate('New notifications') + ': ' + count);

            this.$number.removeClass('hidden').html(count.toString());
        },

        hideNotRead: function () {
            this.$badge.attr('title', this.translate('Notifications'));
            this.$number.addClass('hidden').html('');
        },

        checkUpdates: function (isFirstCheck) {
            if (!this.checkIntervalConditions()) {
                return;
            }

            let url = 'data/notReadCount.json?time=' + $.now();
            $.ajax(url, {local: true}).done(function (response) {
                // prepare count
                var count = 0;
                if (typeof response[this.getUser().id] != 'undefined') {
                    count = response[this.getUser().id];
                }

                if (!isFirstCheck && count > this.unreadCount && $('#nofitication.alert-danger').length === 0) {
                    if ((count - this.unreadCount) > 1) {
                        Espo.Ui.notify(this.translate('youHaveNewNotifications'), 'info', 5000);
                    } else {
                        Espo.Ui.notify(this.translate('youHaveNewNotification'), 'info', 5000);
                    }
                }

                this.unreadCount = count;
                localStorage.setItem('unreadCount', this.unreadCount);

                if (count) {
                    this.showNotRead(count);
                } else {
                    this.hideNotRead();
                }
            }.bind(this));
        },

        runCheckUpdates: function (isFirstCheck) {
            this.checkUpdates(isFirstCheck);

            this.timeout = setTimeout(function () {
                this.runCheckUpdates();
            }.bind(this), this.notificationsCheckInterval * 1000);
        },

        showNotifications: function () {
            this.closeNotifications();

            this.$el.addClass('open');

            var $container = $('<div>').attr('id', 'notifications-panel');

            $container.appendTo(this.$el.find('.notifications-panel-container'));

            this.createView('panel', 'views/notification/panel', {
                el: '#notifications-panel',
            }, function (view) {
                view.render();
                this.listenTo(view, 'all-read', function () {
                    this.hideNotRead();
                    this.$el.find('.badge-circle-warning').remove();
                }, this);
            }.bind(this));

            $document = $(document);
            $document.on('mouseup.notification', function (e) {
                if (!$container.is(e.target) && $container.has(e.target).length === 0
                    && !this.$el.is(e.target) && this.$el.has(e.target).length === 0) {
                    if (!$(e.target).closest('div.modal-dialog').size()) {
                        this.closeNotifications();
                    }
                }
            }.bind(this));
        },

        closeNotifications: function () {
            this.$el.removeClass('open');

            $container = $('#notifications-panel');

            $('#notifications-panel').remove();
            $document = $(document);
            if (this.hasView('panel')) {
                this.clearView('panel');
            }
            $document.off('mouseup.notification');
            $container.remove();
        },

        checkIntervalConditions() {
            let check = true;

            (this.intervalConditions || []).forEach(condition => {
                if (typeof condition === 'function') {
                    check = check && condition.call(this);
                }
            });

            return check;
        }

    });

});
