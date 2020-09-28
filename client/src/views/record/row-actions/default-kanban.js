

Espo.define('views/record/row-actions/default-kanban', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            var list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                },
                link: '#' + this.model.name + '/view/' + this.model.id
            }];
            if (this.options.statusFieldIsEditable) {
                list.push({
                    action: 'moveOver',
                    label: 'Move Over',
                    data: {
                        id: this.model.id
                    }
                });
            }
            if (this.options.acl.edit) {
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id
                    },
                    link: '#' + this.model.name + '/edit/' + this.model.id
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
