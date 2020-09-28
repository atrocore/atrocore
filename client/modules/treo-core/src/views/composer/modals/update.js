

Espo.define('treo-core:views/composer/modals/update', 'treo-core:views/composer/modals/install',
    Dep => Dep.extend({

        setupHeader() {
            this.header = this.translate('updateModule', 'labels', 'Composer');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'save',
                    label: this.translate('updateModule', 'labels', 'Composer'),
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

    })
);