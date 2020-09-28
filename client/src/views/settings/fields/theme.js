
Espo.define('views/settings/fields/theme', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.params.options = Object.keys(this.getMetadata().get('themes')).sort(function (v1, v2) {
                return this.translate(v1, 'theme').localeCompare(this.translate(v2, 'theme'));
            }.bind(this));

            this.params.isSorted = true;

            Dep.prototype.setup.call(this);
        },

    });

});
