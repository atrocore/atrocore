

Espo.define('views/preferences/fields/time-zone', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            this.params.options = Espo.Utils.clone(this.getConfig().getFieldParam('timeZone', 'options') || []);
            this.params.options.unshift('');

            this.translatedOptions = this.translatedOptions || {};
            this.translatedOptions[''] = this.translate('Default') + ' (' + this.getConfig().get('timeZone') +')';
        },

    });

});
