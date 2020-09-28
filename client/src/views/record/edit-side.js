

Espo.define('views/record/edit-side', 'views/record/detail-side', function (Dep) {

    return Dep.extend({

        mode: 'edit',

        defaultPanelDefs: {
            name: 'default',
            label: false,
            view: 'views/record/panels/side',
            isForm: true,
            options: {
                fieldList: [
                    {
                        name: ':assignedUser'
                    },
                    {
                        name: 'teams',
                        view: 'views/fields/teams'
                    }
                ]
            }
        },

    });
});
