

Espo.define('views/notification/items/system', 'views/notification/items/base', function (Dep) {

    return Dep.extend({

        template: 'notification/items/system',

        data: function () {
            var data = Dep.prototype.data.call(this);
            data['message'] = this.model.get('message');
            return data;
        },

        setup: function () {
            var data = this.model.get('data') || {};
            this.userId = data.userId;
        }

    });
});

