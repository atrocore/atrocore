

Espo.define('treo-core:views/fields/overview-locales-filter', 'treo-core:views/fields/dropdown-enum',
    Dep => Dep.extend({

        optionsList: [
            {
                name: '',
                selectable: true
            },
            {
                name: 'showGenericFields',
                action: 'showGenericFields',
                field: true,
                type: 'bool',
                view: 'treo-core:views/fields/bool-with-inline-label',
                default: true
            }
        ],

        prepareOptionsList() {
            let locales = this.getConfig().get('inputLanguageList') || [];
            if (this.getConfig().get('isMultilangActive') && locales.length) {
                locales.forEach((locale, index) => {
                    if (!this.optionsList.find(item => item.name === locale)) {
                        let item = {
                            name: locale,
                            selectable: true,
                            label: this.getLanguage().translateOption(locale, 'language', 'Global')
                        };
                        this.optionsList.splice(1 + index, 0, item);
                    }
                });
            }

            Dep.prototype.prepareOptionsList.call(this);
        }

    })
);