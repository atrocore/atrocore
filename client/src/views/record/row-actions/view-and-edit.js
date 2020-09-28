

Espo.define('views/record/row-actions/view-and-edit', 'views/record/row-actions/default', function (Dep) {

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
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'quickEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        },
                        link: '#' + this.model.name + '/edit/' + this.model.id
                    }
                ]);
            }
            return list;
        }

    });

});


