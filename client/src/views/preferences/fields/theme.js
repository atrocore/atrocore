

Espo.define('views/preferences/fields/theme', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.params.options = Object.keys(this.getMetadata().get('themes')).sort(function (v1, v2) {
                return this.translate(v1, 'themes').localeCompare(this.translate(v2, 'themes'));
            }.bind(this));

            this.params.options.unshift('');

            Dep.prototype.setup.call(this);

            this.translatedOptions = this.translatedOptions || {};

            var defaultTranslated = this.translatedOptions[this.getConfig().get('theme')] || this.getConfig().get('theme');

            this.translatedOptions[''] = this.translate('Default') + ' (' + defaultTranslated + ')';
        },

    });

});
