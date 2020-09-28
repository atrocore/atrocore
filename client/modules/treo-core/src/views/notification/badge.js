

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
