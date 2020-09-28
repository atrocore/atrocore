

Espo.define('views/import/record/panels/updated', 'views/import/record/panels/imported', function (Dep) {

    return Dep.extend({

        link: 'updated',

        rowActionsView: 'views/record/row-actions/relationship-view-and-edit',

        setup: function () {
            this.title = this.title || this.translate('Updated', 'labels', 'Import');
            Dep.prototype.setup.call(this);
        },

    });
});
