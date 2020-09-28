

Espo.define('views/stream/row-actions/default', 'views/record/row-actions/edit-and-remove', function (Dep) {

    return Dep.extend({


        getActionList: function () {
            var list = [];

            if (this.options.acl.edit && this.options.isEditable) {
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (this.options.acl.edit && this.options.isRemovable) {
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

