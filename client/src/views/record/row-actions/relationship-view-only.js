

Espo.define('views/record/row-actions/relationship-view-only', 'views/record/row-actions/relationship', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            return [
                {
                    action: 'viewRelated',
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

