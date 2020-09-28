
Espo.define('views/preferences/fields/auto-follow-entity-type-list', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.params.options = Object.keys(this.getMetadata().get('scopes')).filter(function (scope) {
                return this.getMetadata().get('scopes.' + scope + '.entity') && this.getMetadata().get('scopes.' + scope + '.stream');
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            Dep.prototype.setup.call(this);
        },

    });
});
