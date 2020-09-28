

Espo.define('treo-core:views/stream/notes/update-module', 'views/stream/note',
    Dep =>  Dep.extend({

        template: 'treo-core:stream/notes/update-module',

        isEditable: false,

        isRemovable: false,

        messageName: 'updateModule',

        data() {
            let data = Dep.prototype.data.call(this);
            data.package = this.getPackage();
            return data;
        },

        setup() {
            this.createMessage();
        },

        getPackage() {
            let locale = this.getPreferences().get('language') || this.getConfig().get('language');
            let package = (this.model.get('data') || {}).package || {};
            let names = (package.extra || {}).name || {};
            return {
                id: package.name,
                name: names[locale] || names['default'] || package.name,
                version: package.version
            };
        }
    })
);

