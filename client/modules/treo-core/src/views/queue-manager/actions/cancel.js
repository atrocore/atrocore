

Espo.define('treo-core:views/queue-manager/actions/cancel', 'treo-core:views/queue-manager/actions/close',
    Dep => Dep.extend({

        buttonLabel: 'cancel',

        getSaveData () {
            return {
                status: 'Canceled'
            };
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.disabled = this.model.get('status') === 'Running';
        }

    })
);

