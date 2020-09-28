

Espo.define('views/admin/entity-manager/record/edit-formula', 'views/record/base', function (Dep) {

    return Dep.extend({

        template: 'admin/entity-manager/record/edit-formula',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.createField('beforeSaveCustomScript', 'views/fields/formula', {
                targetEntityType: this.options.targetEntityType,
                height: 500
            }, 'edit');
        }

    });
});

