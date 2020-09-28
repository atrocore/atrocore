

Espo.define('treo-core:views/queue-manager/actions/show-message', 'treo-core:views/queue-manager/actions/abstract-action',
    Dep => Dep.extend({

        template: 'treo-core:queue-manager/actions/show-message',

        buttonLabel: 'showMessage',

        data() {
            return _.extend({
                showButton: !!this.actionData.message
            }, Dep.prototype.data.call(this));
        },

        actionShowMessageModal() {
            this.createView('modal', 'treo-core:views/queue-manager/modals/show-message', {
                message: this.actionData.message
            }, view => view.render());
        }

    })
);

