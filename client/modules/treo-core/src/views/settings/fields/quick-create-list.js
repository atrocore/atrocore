

Espo.define('treo-core:views/settings/fields/quick-create-list', ['class-replace!treo-core:views/settings/fields/quick-create-list', 'views/fields/array'],
    (Dep, Array) => Dep.extend({
        setup: function () {
            this.params.options = Object.keys(this.getMetadata().get('scopes')).filter(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled')) return
                if (this.getMetadata().get('scopes.' + scope + '.quickCreateListDisabled')) return

                return this.getMetadata().get('scopes.' + scope + '.entity') && this.getMetadata().get('scopes.' + scope + '.object');
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            Array.prototype.setup.call(this);
        }
    })
);