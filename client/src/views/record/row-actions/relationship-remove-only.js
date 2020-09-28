

Espo.define('views/record/row-actions/relationship-remove-only', 'views/record/row-actions/relationship', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            if (this.options.acl.delete) {
                return [
                    {
                        action: 'removeRelated',
                        label: 'Remove',
                        data: {
                            id: this.model.id
                        }
                    }
                ];
            }
        }

    });

});

