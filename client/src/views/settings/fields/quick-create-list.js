
Espo.define('views/settings/fields/quick-create-list', 'views/fields/array', function (Dep) {

    return Dep.extend({

        setup: function () {

            this.params.options =  Object.keys(this.getMetadata().get('scopes')).filter(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled')) return;
                return this.getMetadata().get('scopes.' + scope + '.entity') && this.getMetadata().get('scopes.' + scope + '.object');
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            Dep.prototype.setup.call(this);
        }

    });

});
