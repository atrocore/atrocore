

Espo.define('treo-core:views/queue-manager/modals/show-message', 'views/modal',
    Dep => Dep.extend({

        className: 'dialog queue-modal',

        template: 'treo-core:queue-manager/modals/show-message',

        buttonList: [
            {
                name: 'cancel',
                label: 'Close'
            }
        ],

        setup() {
            Dep.prototype.setup.call(this);

            this.header = this.translate('message', 'labels', 'QueueItem');
        },

        data() {
            return {
                message: this.options.message
            };
        },

    })
);

