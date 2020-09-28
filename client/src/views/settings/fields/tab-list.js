
Espo.define('views/settings/fields/tab-list', 'views/fields/array', function (Dep) {

    return Dep.extend({

        setupOptions: function () {

            this.params.options = Object.keys(this.getMetadata().get('scopes')).filter(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled')) return;
                return this.getMetadata().get('scopes.' + scope + '.tab');
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            this.params.options.push('_delimiter_');

            this.translatedOptions = {};

            this.params.options.forEach(function (item) {
                this.translatedOptions[item] = this.translate(item, 'scopeNamesPlural');
            }, this);

            this.translatedOptions['_delimiter_'] = '. . .';
        }

    });

});
