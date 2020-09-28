

Espo.define('views/record/row-actions/relationship-view-and-unlink', 'views/record/row-actions/relationship', function (Dep) {

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
                list.push({
                    action: 'unlinkRelated',
                    label: 'Unlink',
                    data: {
                        id: this.model.id
                    }
                });
            }
            return list;
        },

    });

});

