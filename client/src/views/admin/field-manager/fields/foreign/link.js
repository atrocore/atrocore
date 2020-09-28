

Espo.define('views/admin/field-manager/fields/foreign/link', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            if (!this.model.isNew()) {
                this.setReadOnly(true);
            }
        },

        setupOptions: function () {
            var links = this.getMetadata().get(['entityDefs', this.options.scope, 'links']) || {};

            this.params.options = Object.keys(Espo.Utils.clone(links)).filter(function (item) {
                if (links[item].type !== 'belongsTo') return;
                if (links[item].noJoin) return;

                return true;
            }, this);

            this.translatedOptions = {};
            this.params.options.forEach(function (item) {
                this.translatedOptions[item] = this.translate(item, 'links', this.options.scope);
            }, this);

            this.params.options.unshift('');
        }

    });

});
