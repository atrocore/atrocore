

Espo.define('treo-core:views/admin/field-manager/fields/measure-options', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew()) {
                this.setReadOnly();
            }
        },

        setupOptions() {
            this.params.options = Object.keys(Espo.Utils.cloneDeep(this.getConfig().get('unitsOfMeasure') || {}));
        },
    })
);