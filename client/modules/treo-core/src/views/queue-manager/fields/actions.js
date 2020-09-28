

Espo.define('treo-core:views/queue-manager/fields/actions', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'treo-core:queue-manager/fields/actions/list',

        defaultActionDefs: {
            view: 'treo-core:views/queue-manager/actions/show-message'
        },

        data() {
            return {
                actions: this.model.get(this.name) || []
            };
        },

        afterRender() {
            this.buildActions();
        },

        buildActions() {
            (this.model.get(this.name) || []).forEach(action => {
                let actionDefs = this.getMetadata().get(['clientDefs', 'QueueItem', 'queueActions', action.type]) || this.defaultActionDefs;
                if (actionDefs.view && this.getAcl().check(this.model, actionDefs.acl)) {
                    this.createView(action.type, actionDefs.view, {
                        el: `${this.options.el} .queue-manager-action[data-type="${action.type}"]`,
                        actionData: action.data,
                        model: this.model
                    }, view => {
                        this.listenTo(view, 'reloadList', () => {
                            this.model.trigger('reloadList');
                        });
                        view.render();
                    });
                }
            });
        }

    })
);

