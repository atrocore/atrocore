

Espo.define('views/notification/fields/container', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'notification',

        listTemplate: 'notification/fields/container',

        detailTemplate: 'notification/fields/container',

        setup: function () {
            switch (this.model.get('type')) {
                case 'Note':
                    this.processNote(this.model.get('noteData'));
                    break;
                case 'MentionInPost':
                    this.processMentionInPost(this.model.get('noteData'));
                    break;
                default:
                    this.process();
            }
        },

        process: function () {
            var type = this.model.get('type');
            if (!type) return;
            type = type.replace(/ /g, '');

            var viewName = this.getMetadata().get('clientDefs.Notification.itemViews.' + type) || 'views/notification/items/' + Espo.Utils.camelCaseToHyphen(type);
            this.createView('notification', viewName, {
                model: this.model,
                el: this.params.containerEl + ' li[data-id="' + this.model.id + '"]',
            });
        },

        processNote: function (data) {
            this.wait(true);
            this.getModelFactory().create('Note', function (model) {
                model.set(data);

                var viewName = this.getMetadata().get('clientDefs.Note.itemViews.' + data.type) || 'views/stream/notes/' + Espo.Utils.camelCaseToHyphen(data.type);
                this.createView('notification', viewName, {
                    model: model,
                    isUserStream: true,
                    el: this.params.containerEl + ' li[data-id="' + this.model.id + '"]',
                    onlyContent: true,
                    isNotification: true
                });
                this.wait(false);
            }, this);
        },

        processMentionInPost: function (data) {
            this.wait(true);
            this.getModelFactory().create('Note', function (model) {
                model.set(data);
                var viewName = 'views/stream/notes/mention-in-post';
                this.createView('notification', viewName, {
                    model: model,
                    userId: this.model.get('userId'),
                    isUserStream: true,
                    el: this.params.containerEl + ' li[data-id="' + this.model.id + '"]',
                    onlyContent: true,
                    isNotification: true
                });
                this.wait(false);
            }, this);
        },

    });
});

