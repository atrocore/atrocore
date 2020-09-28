

Espo.define('treo-core:views/settings/record/row-actions/only-quick-edit', 'views/record/row-actions/default',
    Dep => Dep.extend({

        getActionList() {
            if (this.options.acl.edit) {
                return [{
                    action: 'quickEditCustom',
                    label: 'Edit',
                    data: {
                        id: this.model.id,
                        noFullForm: true
                    }
                }];
            }
        }

    })
);
