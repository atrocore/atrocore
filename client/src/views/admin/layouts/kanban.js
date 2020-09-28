

Espo.define('views/admin/layouts/kanban', 'views/admin/layouts/list', function (Dep) {

    return Dep.extend({

        dataAttributeList: ['name', 'link', 'align', 'view', 'isLarge'],

        dataAttributesDefs: {
            link: {type: 'bool'},
            isLarge: {type: 'bool'},
            width: {type: 'float'},
            align: {
                type: 'enum',
                options: ["left", "right"]
            },
            view: {
                type: 'varchar',
                readOnly: true
            },
            name: {
                type: 'varchar',
                readOnly: true
            }
        },

        editable: true,

        ignoreList: [],

        ignoreTypeList: []

    });
});
