

Espo.define('views/email-folder/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        rowActionsView: 'views/email-folder/record/row-actions/default',

        actionMoveUp: function (data) {
            var model = this.collection.get(data.id);
            if (!model) return;

            var index = this.collection.indexOf(model);
            if (index === 0) return;

            this.ajaxPostRequest('EmailFolder/action/moveUp', {
                id: model.id
            }).then(function () {
                this.collection.fetch();
            }.bind(this));
        },

        actionMoveDown: function (data) {
            var model = this.collection.get(data.id);
            if (!model) return;

            var index = this.collection.indexOf(model);
            if ((index === this.collection.length - 1) && (this.collection.length === this.collection.total)) return;

            this.ajaxPostRequest('EmailFolder/action/moveDown', {
                id: model.id
            }).then(function () {
                this.collection.fetch();
            }.bind(this));
        },

    });
});

