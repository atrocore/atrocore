

Espo.define('views/import/record/panels/duplicates', 'views/import/record/panels/imported', function (Dep) {

    return Dep.extend({

        link: 'duplicates',

        setup: function () {
            this.title = this.title || this.translate('Duplicates', 'labels', 'Import');
            Dep.prototype.setup.call(this);
        },

        actionUnmarkAsDuplicate: function (data) {
            var id = data.id;
            var type = data.type;

            this.confirm(this.translate('confirmation', 'messages'), function () {
                this.ajaxPostRequest('Import/action/unmarkAsDuplicate', {
                    id: this.model.id,
                    entityId: id,
                    entityType: type
                }).then(function () {
                    this.collection.fetch();
                });
            }, this);
        }

    });
});
