

Espo.define('treo-core:views/queue-manager/actions/close', 'treo-core:views/queue-manager/actions/abstract-action',
    Dep => Dep.extend({

        buttonLabel: 'close',

        runAction() {
            this.ajaxPutRequest(`${this.model.name}/${this.model.id}`, this.getSaveData())
                .then(() => this.model.trigger('reloadList'));
        },

        getSaveData() {
            return {
                status: 'Closed'
            };
        }

    })
);

