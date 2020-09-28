

Espo.define('views/notification/fields/read', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'read',

        listTemplate: 'notification/fields/read',

        detailTemplate: 'notification/fields/read',

        data: function () {
            return {
                isRead: this.model.get('read')
            };
        },

    });
});

