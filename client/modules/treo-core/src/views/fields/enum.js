

Espo.define('treo-core:views/fields/enum', 'class-replace!treo-core:views/fields/enum',
    Dep => Dep.extend({

        prohibitedEmptyValue: false,

        prohibitedScopes: ['Settings', 'EntityManager'],

        setup() {
            Dep.prototype.setup.call(this);

            this.prohibitedEmptyValue = this.prohibitedEmptyValue || this.options.prohibitedEmptyValue
                || this.model.getFieldParam(this.name, 'prohibitedEmptyValue');

            if (!this.prohibitedEmptyValue) {
                const scopeIsAllowed = !this.prohibitedScopes.includes(this.model.name);
                const isArray = Array.isArray((this.params || {}).options);

                if (isArray && scopeIsAllowed && !this.params.options.includes('') && this.params.options.length > 1) {
                    this.params.options.unshift('');

                    if (Espo.Utils.isObject(this.translatedOptions)) {
                        this.translatedOptions[''] = '';
                    }

                    if (this.model.isNew() && this.mode === 'edit' && !this.model.get('_duplicatingEntityId')) {
                        this.model.set({[this.name]: ''});
                    }
                }
            }
        }

    })
);
