

Espo.define('views/preferences/fields/time-format', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            this.params.options = Espo.Utils.clone(this.params.options);
            this.params.options.unshift('');

            this.translatedOptions = this.translatedOptions || {};
            this.translatedOptions[''] = this.translate('Default') + ' (' + this.getConfig().get('timeFormat') +')';
        },

    });

});
