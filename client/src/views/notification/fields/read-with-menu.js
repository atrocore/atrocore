

Espo.define('views/notification/fields/read-with-menu', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'read',

        listTemplate: 'notification/fields/read-with-menu',

        detailTemplate: 'notification/fields/read-with-menu',

        data: function () {
            return {
                isRead: this.model.get('read')
            };
        },

    });
});

