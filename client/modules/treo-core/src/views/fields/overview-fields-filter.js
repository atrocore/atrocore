

Espo.define('treo-core:views/fields/overview-fields-filter', 'treo-core:views/fields/dropdown-enum',
    Dep => Dep.extend({

        optionsList: [
            {
                name: '',
                selectable: true
            },
            {
                name: 'empty',
                selectable: true
            },
            {
                name: 'emptyAndRequired',
                selectable: true
            }
        ]

    })
);