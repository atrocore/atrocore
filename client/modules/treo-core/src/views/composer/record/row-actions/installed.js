

Espo.define('treo-core:views/composer/record/row-actions/installed', 'views/record/row-actions/default',
    Dep => Dep.extend({

        disableActions: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model.collection, 'disableActions', (disableActions) => {
                this.disableActions = disableActions;
                this.reRender();
            });
        },

        getActionList() {
            let list = [];
            if (!this.disableActions && this.model.get('isComposer')) {
                if (!this.model.get('status')) {
                    if (!this.model.get('isSystem')) {
                        list.push({
                            action: 'installModule',
                            label: 'updateModule',
                            data: {
                                id: this.model.id,
                                mode: 'update'
                            }
                        });
                    }
                    let checkRequire = this.model.collection.every(model => !(model.get('required') || []).includes(this.model.get('id')));
                    if (checkRequire && !this.model.get('isSystem')) {
                        list.push({
                            action: 'removeModule',
                            label: 'removeModule',
                            data: {
                                id: this.model.id
                            }
                        });
                    }
                } else {
                    list.push({
                        action: 'cancelModule',
                        label: 'cancelModule',
                        data: {
                            id: this.model.id,
                            status: this.model.get('status')
                        }
                    });
                }
            }
            return list;
        },

    })
);
