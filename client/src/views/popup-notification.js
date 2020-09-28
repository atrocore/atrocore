

Espo.define('views/popup-notification', 'view', function (Dep) {

    return Dep.extend({

        type: 'default',

        style: 'default',

        closeButton: true,

        soundPath: 'client/sounds/pop_cork',

        init: function () {
            Dep.prototype.init.call(this);

            var id = this.options.id;
            var containerSelector = this.containerSelector = '#' + id;

            this.setSelector(containerSelector);

            this.notificationSoundsDisabled = this.getConfig().get('notificationSoundsDisabled');

            this.soundPath = this.getBasePath() + (this.getConfig().get('popupNotificationSound') || this.soundPath);

            this.on('render', function () {
                $(containerSelector).remove();

                var className = 'popup-notification-' + Espo.Utils.toDom(this.type);

                $('<div>').attr('id', id)
                          .addClass('popup-notification')
                          .addClass(className)
                          .addClass('popup-notification-' + this.style)
                          .appendTo('#popup-notifications-container');
                this.setElement(containerSelector);
            }, this);

            this.on('after:render', function () {
                this.$el.find('[data-action="close"]').on('click', function () {
                    this.cancel();
                }.bind(this));
            }, this);

            this.once('after:render', function () {
                this.onShow();
            }.bind(this));

            this.once('remove', function () {
                $(containerSelector).remove();
            });

            this.notificationData = this.options.notificationData;
            this.notificationId = this.options.notificationId;
            this.id = this.options.id;
        },

        data: function () {
            return {
                closeButton: this.closeButton,
                notificationData: this.notificationData,
                notificationId: this.notificationId
            };
        },

        playSound: function () {
            if (this.notificationSoundsDisabled) return;

            var html = '' +
                '<audio autoplay="autoplay">'+
                    '<source src="' + this.soundPath + '.mp3" type="audio/mpeg" />'+
                    '<source src="' + this.soundPath + '.ogg" type="audio/ogg" />'+
                    '<embed hidden="true" autostart="true" loop="false" src="' + this.soundPath +'.mp3" />'+
                '</audio>';
            $(html).get(0).volume = 0.3;
            $(html).get(0).play();
        },

        onShow: function () {
            if (!this.options.isFirstCheck) {
                this.playSound();
            }
        },

        onConfirm: function () {
        },

        onCancel: function () {
        },

        confirm: function () {
            this.onConfirm();
            this.trigger('confirm');
            this.remove();
        },

        cancel: function () {
            this.onCancel();
            this.trigger('cancel');
            this.remove();
        }

    });
});

