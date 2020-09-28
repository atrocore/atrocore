

Espo.define('views/notification/items/assign', 'views/notification/items/base', function (Dep) {

    return Dep.extend({

        messageName: 'assign',

        template: 'notification/items/assign',

        setup: function () {
            var data = this.model.get('data') || {};

            this.userId = data.userId;

            this.messageData['entityType'] = Espo.Utils.upperCaseFirst((this.translate(data.entityType, 'scopeNames') || '').toLowerCase());
            this.messageData['entity'] = '<a href="#' + data.entityType + '/view/' + data.entityId + '">' + data.entityName + '</a>';

            this.createMessage();
        },

    });
});

