

Espo.define('views/notification/items/entity-removed', 'views/notification/items/base', function (Dep) {

    return Dep.extend({

        messageName: 'entityRemoved',

        template: 'notification/items/entity-removed',

        setup: function () {
            var data = this.model.get('data') || {};

            this.userId = data.userId;

            this.messageData['entityType'] = (this.translate(data.entityType, 'scopeNames') || '').toLowerCase();

            this.messageData['user'] = '<a href="#User/view/' + data.userId + '">' + data.userName + '</a>';
            this.messageData['entity'] = '<a href="#'+data.entityType+'/view/' + data.entityId + '">' + data.entityName + '</a>';

            this.createMessage();
        }

    });
});

