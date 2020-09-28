

Espo.define('views/role/record/detail-side', 'views/record/detail-side', function (Dep) {

    return Dep.extend({

        panelList: [
            {
                name: 'default',
                label: false,
                view: 'views/role/record/panels/side'
            }
        ],

    });
});


