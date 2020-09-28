

Espo.define('views/email/record/detail-side', 'views/record/detail-side', function (Dep) {

    return Dep.extend({

        defaultPanelDefs: {
            name: 'default',
            label: false,
            view: 'views/record/panels/default-side',
            options: {
                fieldList: [
                    {
                        name: 'teams',
                        view: 'views/fields/teams'
                    },
                    'replied',
                    'replies'
                ]
            },
            isForm: true
        }

    });
});
