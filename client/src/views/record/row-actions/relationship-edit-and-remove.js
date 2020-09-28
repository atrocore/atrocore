

Espo.define('views/record/row-actions/relationship-edit-and-remove', 'views/record/row-actions/relationship', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            var list = [];
            if (this.options.acl.edit) {
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id
                    }
                });
            }
            if (this.options.acl.delete) {
                list.push({
                    action: 'quickRemove',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }
            return list;
        }

    });

});
