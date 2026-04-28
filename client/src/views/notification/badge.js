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

            const processPublicData = data => {
                this.$number?.addClass('hidden').html('');

                if (data.notReadCount) {
                    let unreadCount = JSON.parse(data.notReadCount);
                    if (unreadCount && unreadCount[this.getUser().id]) {
                        let count = unreadCount[this.getUser().id];
                        if (count) {
                            if (this.$badge && this.$badge.length > 0) {
                                this.$number?.removeClass('hidden').html(count.toString());
                            }

                            if (savedCount && count > savedCount) {
                                const newCount = count - savedCount;
                                this.ajaxGetRequest('Notification', {
                                    maxSize: newCount,
                                    offset: 0,
                                    where: [{type: 'isFalse', attribute: 'read'}]
                                }).done(response => {
                                    (response.list || []).forEach(notification => {
                                        const data = notification.data || {};
                                        const messageTemplate = notification.message || data.message || '';

                                        if (!messageTemplate) {
                                            window.Notifier.notify(this.translate('youHaveNewNotification'), {type: 'info', duration: 5000});
                                            return;
                                        }

                                        const messageData = {
                                            entityType: Espo.Utils.upperCaseFirst((this.translate(data.entityType, 'scopeNames') || '').toLowerCase()),
                                            user: '<a href="#User/view/' + data.userId + '">' + data.userName + '</a>',
                                            entity: '<a href="#' + data.entityType + '/view/' + data.entityId + '">' + data.entityName + '</a>',
                                        };

                                        const viewKey = 'notifMsg_' + notification.id;
                                        this.createView(viewKey, 'views/stream/message', {
                                            messageTemplate,
                                            messageData,
                                        }, view => {
                                            const html = view._template || messageTemplate;
                                            this.clearView(viewKey);
                                            window.Notifier.notify(html, {type: 'info', duration: 5000});
                                        });
                                    });
                                });
                            }
                            savedCount = count;

                            if (navigator.setAppBadge && this.isPWAMode()) {
                                navigator.setAppBadge(count);
                            }

                            this.refreshList();
                            return;
                        }
                    }
                }

                if (navigator.clearAppBadge) {
                    navigator.clearAppBadge();
                }

                this.refreshList();
            }

            this.listenTo(Backbone.Events, 'publicData', processPublicData);

            this.listenToOnce(this, 'remove', () => {
                this.stopListening(Backbone.Events, 'publicData', processPublicData);
            });
        },

        afterRender: function () {
            this.$badge = this.$el.find('.notifications-button');
            this.$number = this.$el.find('.number-badge');

            $('body').attr({'style': ''});
        },

        refreshList: function () {
            const key = this.getUser().id + 'RefreshListScope';
            const refreshScope = localStorage.getItem('pd_' + key) || null;

            let expectedHash = "#" + refreshScope;
            if (refreshScope && expectedHash === window.location.hash) {
                this.ajaxPostRequest('publicData', {
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
