

Espo.define('views/import/record/panels/imported', 'views/record/panels/relationship', function (Dep) {

    return Dep.extend({

        link: 'imported',

        readOnly: true,

        rowActionsView: 'views/record/row-actions/relationship-no-unlink',

        setup: function () {
            this.scope = this.model.get('entityType');
            this.title = this.title || this.translate('Imported', 'labels', 'Import');
            Dep.prototype.setup.call(this);
        }

    });
});
