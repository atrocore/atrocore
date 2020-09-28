

Espo.define('treo-core:views/admin/layouts/list-small', 'class-replace!treo-core:views/admin/layouts/list-small',
    Dep => Dep.extend({

        isFieldEnabled(model, name) {
            return !model.getFieldParam(name, 'layoutListSmallDisabled') && Dep.prototype.isFieldEnabled.call(this, model, name);
        },

    })
);


