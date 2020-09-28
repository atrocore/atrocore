

Espo.define('views/stream/notes/status', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/status',

        messageName: 'status',

        data: function () {
            return _.extend({
                style: this.style,
                statusText: this.statusText,
            }, Dep.prototype.data.call(this));
        },

        init: function () {
            if (this.getUser().isAdmin()) {
                this.isRemovable = true;
            }
            Dep.prototype.init.call(this);
        },

        setup: function () {
            var data = this.model.get('data');

            var field = data.field;
            var value = data.value;

            this.style = data.style || 'default';

            this.statusText = this.getLanguage().translateOption(value, field, this.model.get('parentType'));

            this.messageData['field'] = this.translate(field, 'fields', this.model.get('parentType')).toLowerCase();

            this.createMessage();
        },

    });
});

