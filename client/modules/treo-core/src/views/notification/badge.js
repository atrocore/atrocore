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

Espo.define('treo-core:views/notification/badge', 'class-replace!treo-core:views/notification/badge',
    Dep => Dep.extend({

        intervalConditions: [],

        setup() {
            this.intervalConditions = this.options.intervalConditions || this.intervalConditions;

            Dep.prototype.setup.call(this);
        },

        checkUpdates: function (isFirstCheck) {
            if (!this.checkIntervalConditions()) {
                return;
            }

            if (this.checkBypass()) {
                return;
            }

            let url = 'data/notReadCount.json?time=' + $.now();
            $.ajax(url, {local: true}).done(function (response) {
                // prepare count
                var count = 0;
                if (typeof response[this.getUser().id] != 'undefined') {
                    count = response[this.getUser().id];
                }

                if (!isFirstCheck && count > this.unreadCount) {

                    var blockPlayNotificationSound = localStorage.getItem('blockPlayNotificationSound');
                    if (!blockPlayNotificationSound) {
                        this.playSound();
                        localStorage.setItem('blockPlayNotificationSound', true);
                        setTimeout(function () {
                            delete localStorage['blockPlayNotificationSound'];
                        }, this.notificationsCheckInterval * 1000);
                    }
                }
                this.unreadCount = count;
                if (count) {
                    this.showNotRead(count);
                } else {
                    this.hideNotRead();
                }
            }.bind(this));
        },

        checkPopupNotifications: function (name) {
            var data = this.popupNotificationsData[name] || {};

            console.log(data);

            var url = 'data/popupNotifications.json?time=' + $.now();
            var interval = data.interval;
            var disabled = data.disabled || false;

            if (disabled || !url || !interval) return;

            var isFirstCheck = false;
            if (this.popupCheckIteration == 0) {
                isFirstCheck = true;
            }

            (new Promise(function (resolve) {
                if (this.checkBypass()) {
                    resolve();
                    return;
                }

                if (!this.checkIntervalConditions()) {
                    resolve();
                    return;
                }
                var jqxhr = $.ajax(url, {local: true}).done(function (response) {
                    // prepare list
                    var list = [];
                    if (typeof response[this.getUser().id] != 'undefined') {
                        list = response[this.getUser().id];
                    }

                    list.forEach(function (d) {
                        this.showPopupNotification(name, d, isFirstCheck);
                    }, this);
                }.bind(this));

                jqxhr.always(function() {
                    resolve();
                });
            }.bind(this))).then(function () {
                this.popoupTimeouts[name] = setTimeout(function () {
                    this.popupCheckIteration++;
                    this.checkPopupNotifications(name);
                }.bind(this), interval * 1000);
            }.bind(this));
        },

        checkIntervalConditions() {
            let check = true;

            (this.intervalConditions || []).forEach(condition => {
                if (typeof  condition === 'function') {
                    check = check && condition.call(this);
                }
            });

            return check;
        }

    })
);
