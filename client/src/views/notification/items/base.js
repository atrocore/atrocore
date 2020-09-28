

Espo.define('views/notification/items/base', 'view', function (Dep) {

    return Dep.extend({

        messageName: null,

        messageTemplate: null,

        messageData: null,

        isSystemAvatar: true,

        data: function () {
            return {
                avatar: this.getAvatarHtml()
            };
        },

        init: function () {
            this.createField('createdAt', null, null, 'views/fields/datetime-short');

            this.messageData = {};
        },

        createField: function (name, type, params, view) {
            type = type || this.model.getFieldType(name) || 'base';
            this.createView(name, view || this.getFieldManager().getViewName(type), {
                model: this.model,
                defs: {
                    name: name,
                    params: params || {}
                },
                el: this.options.el + ' .cell-' + name,
                mode: 'list'
            });
        },

        createMessage: function () {
            var parentType = this.model.get('relatedParentType') || null;

            if (!this.messageTemplate && this.messageName) {
                this.messageTemplate = this.translate(this.messageName, 'notificationMessages', parentType) || '';
            }

            this.createView('message', 'views/stream/message', {
                messageTemplate: this.messageTemplate,
                el: this.options.el + ' .message',
                model: this.model,
                messageData: this.messageData
            });
        },

        getAvatarHtml: function () {
            var id = this.userId;
            if (this.isSystemAvatar) {
                id = 'system';
            }
            return this.getHelper().getAvatarHtml(id, 'small', 20);
        }

    });
});
