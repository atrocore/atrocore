

Espo.define('views/record/row-actions/relationship-no-remove', 'views/record/row-actions/relationship', function (Dep) {

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
                    },
                    {
                        action: 'unlinkRelated',
                        label: 'Unlink',
                        data: {
                            id: this.model.id
                        }
                    }
                ]);
            }
            return list;
        }

    });

});

