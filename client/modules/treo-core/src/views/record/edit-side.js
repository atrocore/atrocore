

Espo.define('treo-core:views/record/edit-side', 'class-replace!treo-core:views/record/edit-side', function (Dep) {

    return Dep.extend({

        defaultPanelDefs: {
            name: 'default',
            label: 'Ownership Information',
            view: 'views/record/panels/side',
            isForm: true,
            options: {
                fieldList: [
                    {
                        name: 'ownerUser',
                        view: 'views/fields/user-with-avatar'
                    },
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


