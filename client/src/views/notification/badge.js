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

Espo.define('views/notification/badge', 'view', function (Dep) {

    return Dep.extend({

        template: 'notification/badge',

        events: {
            'click a[data-action="showNotifications"]': function (e) {
                if (!this.hasView('panel')) {
                    this.showNotifications();
                } else {
                    this.closeNotifications();
                }
            },
            'click a[data-action="close"]': function () {
                this.closeNotifications();
            }
        },

        setup: function () {

            let savedCount = null;

            this.listenTo(Backbone.Events, 'publicData', data => {
                this.hideNotRead();
                if (data.notReadCount) {
                    let unreadCount = JSON.parse(data.notReadCount);
                    if (unreadCount && unreadCount[this.getUser().id]) {
                        let count = unreadCount[this.getUser().id];
                        if (count) {
                            this.showNotRead(count);
                            if (savedCount && count > savedCount && $('#nofitication.alert-danger').length === 0) {
                                if ((count - savedCount) > 1) {
                                    Espo.Ui.notify(this.translate('youHaveNewNotifications'), 'info', 5000);
                                } else {
                                    Espo.Ui.notify(this.translate('youHaveNewNotification'), 'info', 5000);
                                }
                            }
                            savedCount = count;
                        }
                    }
                }

                this.refreshList();
            });
        },

        afterRender: function () {
            this.$badge = this.$el.find('.notifications-button');
            this.$number = this.$el.find('.number-badge');

            $('body').attr({'style': ''});
        },

        showNotRead: function (count) {
            if (this.$badge && this.$badge.length > 0) {
                this.$badge.attr('title', this.translate('New notifications') + ': ' + count);
                this.$number?.removeClass('hidden').html(count.toString());
            }
        },

        hideNotRead: function () {
            if (this.$badge) {
                this.$badge.attr('title', this.translate('Notifications'));
            }
            if (this.$number) {
                this.$number.addClass('hidden').html('');
            }
        },

        refreshList: function () {
            const key = this.getUser().id + 'RefreshListScope';
            const refreshScope = localStorage.getItem('pd_' + key) || null;

            let expectedHash = "#" + refreshScope;
            if (refreshScope === 'Job') {
                expectedHash = '#Admin/jobs';
            }

            if (refreshScope && expectedHash === window.location.hash) {
                this.ajaxPostRequest('App/action/UpdatePublicDataKey', {
                    key: key,
                    value: null
                }).then(() => {
                    $('button[data-action="search"]').click();
                });
            }
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

    });

});
