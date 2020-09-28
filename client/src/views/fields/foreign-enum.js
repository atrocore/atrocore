

Espo.define('views/fields/foreign-enum', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        type: 'foreign',

        setupOptions: function () {
            this.params.options = [];

            if (!this.params.field || !this.params.link) return;

            var scope = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.params.link, 'entity']);
            if (!scope) {
                return;
            }
            this.params.options = this.getMetadata().get(['entityDefs', scope, 'fields', this.params.field, 'options']) || [];

            this.translatedOptions = {};
            this.params.options.forEach(function(item) {
                this.translatedOptions[item] = this.getLanguage().translateOption(item, this.params.field, scope);
            }, this);
        },

    });
});

