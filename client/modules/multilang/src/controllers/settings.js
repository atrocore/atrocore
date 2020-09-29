

Espo.define('multilang:controllers/settings', ['treo-core:controllers/settings', 'multilang:models/settings'],
    (Dep, Settings) => Dep.extend({

        inputLanguage() {
            let model = this.getSettingsModel();

            model.once('sync', () => {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'multilang:admin/settings/headers/input-language',
                    recordView: 'multilang:views/admin/input-language'
                });
            }, this);
            model.fetch();
        },

        getSettingsModel() {
            let model = new Settings(null);
            model.defs = this.getMetadata().get('entityDefs.Settings');
            return model;
        }

    })
);  