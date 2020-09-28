

Espo.define('views/email-account/fields/test-connection', 'views/fields/base', function (Dep) {

    return Dep.extend({

        readOnly: true,

        _template: '<button class="btn btn-default disabled" data-action="testConnection">{{translate \'Test Connection\' scope=\'EmailAccount\'}}</button>',

        url: 'EmailAccount/action/testConnection',

        events: {
            'click [data-action="testConnection"]': function () {
                this.test();
            },
        },

        fetch: function () {
            return {};
        },

        checkAvailability: function () {
            if (this.model.get('host')) {
                this.$el.find('button').removeClass('disabled');
            } else {
                this.$el.find('button').addClass('disabled');
            }
        },

        afterRender: function () {
            this.checkAvailability();

            this.stopListening(this.model, 'change:host');
            this.listenTo(this.model, 'change:host', function () {
                this.checkAvailability();
            }, this);
        },

        getData: function () {
            var data = {
                'host': this.model.get('host'),
                'port': this.model.get('port'),
                'ssl': this.model.get('ssl'),
                'username': this.model.get('username'),
                'password': this.model.get('password') || null,
                'id': this.model.id
            };
            return data;
        },


        test: function () {
            var data = this.getData();

            var $btn = this.$el.find('button');

            $btn.addClass('disabled');

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            $.ajax({
                url: this.url,
                type: 'POST',
                data: JSON.stringify(data),
                error: function (xhr, status) {
                    var statusReason = xhr.getResponseHeader('X-Status-Reason') || '';
                    statusReason = statusReason.replace(/ $/, '');
                    statusReason = statusReason.replace(/,$/, '');

                    var msg = this.translate('Error');
                    if (xhr.status != 200) {
                        msg += ' ' + xhr.status;
                    }
                    if (statusReason) {
                        msg += ': ' + statusReason;
                    }
                    Espo.Ui.error(msg);
                    console.error(msg);
                    xhr.errorIsHandled = true;
                    $btn.removeClass('disabled');
                }.bind(this)
            }).done(function () {
                $btn.removeClass('disabled');
                Espo.Ui.success(this.translate('connectionIsOk', 'messages', 'EmailAccount'));
            }.bind(this));

        },

    });

});

