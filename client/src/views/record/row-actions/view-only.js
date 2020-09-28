

Espo.define('views/record/row-actions/view-only', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            return [
                {
                    action: 'quickView',
                    label: 'View',
                    data: {
                        id: this.model.id
                    },
                    link: '#' + this.model.name + '/view/' + this.model.id
                }
            ];
        }

    });

});


