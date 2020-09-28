

Espo.define('views/fields/entity-type-list', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        checkAvailability: function (entityType) {
            var defs = this.scopesMetadataDefs[entityType] || {};
            if (defs.entity && defs.object) {
                return true;
            }
        },

        setupOptions: function () {
            var scopes = this.scopesMetadataDefs = this.getMetadata().get('scopes');
            this.params.options = Object.keys(scopes).filter(function (scope) {
                if (this.checkAvailability(scope)) {
                    return true;
                }
            }.bind(this)).sort(function (v1, v2) {
                 return this.translate(v1, 'scopeNames').localeCompare(this.translate(v2, 'scopeNames'));
            }.bind(this));
            this.params.options.unshift('');
        },

        setup: function () {
            if (!this.params.translation) {
                this.params.translation = 'Global.scopeNames';
            }
            this.setupOptions();
            Dep.prototype.setup.call(this);
        }

    });
});

