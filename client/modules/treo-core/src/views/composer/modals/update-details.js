

Espo.define('treo-core:views/composer/modals/update-details', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:composer/modals/update-details',

        setup() {
            Dep.prototype.setup.call(this);

            this.setupHeader();
            this.setupButtonList();
        },

        setupHeader() {
            this.header = this.translate('Details');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

        data() {
            return {
                output: this.options.output
            };
        },

    })
);