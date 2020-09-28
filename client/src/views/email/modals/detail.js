

Espo.define('views/email/modals/detail', ['views/modals/detail', 'views/email/detail'], function (Dep, Detail) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList.unshift({
                'name': 'reply',
                'label': 'Reply',
                'style': 'danger'
            });
            if (this.model) {
                this.listenToOnce(this.model, 'sync', function () {
                    setTimeout(function () {
                        this.model.set('isRead', true);
                    }.bind(this), 50);
                }, this);
            }

        },

        actionReply: function (data, e) {
            Detail.prototype.actionReply.call(this, {}, e, this.getPreferences().get('emailReplyToAllByDefault'));
        }

    });
});

