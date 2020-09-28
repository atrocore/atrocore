

Espo.define('views/admin/field-manager/fields/source-list', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            this.params.options = Espo.Utils.clone(this.getMetadata().get('entityDefs.Attachment.sourceList') || []);

            this.translatedOptions = {};
            this.params.options.forEach(function (item) {
                this.translatedOptions[item] = this.translate(item, 'scopeNamesPlural');
            }, this);
        }
    });

});
