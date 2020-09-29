

Espo.define('multilang:views/admin/fields/input-language-list', 'views/fields/multi-enum',
    Dep => Dep.extend({

        data() {
            return _.extend({
                optionList: this.model.options || []
            }, Dep.prototype.data.call(this));
        },

        setupOptions() {
            this.params.options = Espo.Utils.clone(this.getMetadata().get(['multilang', 'languageList']));
            this.translatedOptions = Espo.Utils.clone(this.getLanguage().translate('language', 'options') || {});
        }

    })
);
