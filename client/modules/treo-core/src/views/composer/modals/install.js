

Espo.define('treo-core:views/composer/modals/install', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:composer/modals/install',

        model: null,

        buttonList: [],

        setup() {
            Dep.prototype.setup.call(this);

            this.model = this.options.currentModel.clone();

            this.prepareAttributes();

            this.createVersionView();

            this.setupHeader();
            this.setupButtonList();
        },

        setupHeader() {
            this.header = this.translate('installModule', 'labels', 'Store');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'save',
                    label: this.translate('installModule', 'labels', 'Store'),
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

        createVersionView() {
            this.createView('settingVersion', 'views/fields/varchar', {
                el: `${this.options.el} .field[data-name="settingVersion"]`,
                model: this.model,
                mode: 'edit',
                defs: {
                    name: 'settingVersion',
                }
            });
        },

        prepareAttributes() {
            let settingVersion = this.model.get('settingVersion');
            if (typeof settingVersion === 'string' && settingVersion.substring(0, 1) == 'v') {
                settingVersion = settingVersion.substr(1);
            }
            if (!settingVersion) {
                settingVersion = '*';
            }

            this.model.set({
                settingVersion: settingVersion
            });
        },

        actionSave() {
            this.trigger('save', {id: this.model.id, version: this.model.get('settingVersion')});
            this.close();
        }

    })
);