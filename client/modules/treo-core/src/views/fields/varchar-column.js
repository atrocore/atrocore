

Espo.define('treo-core:views/fields/varchar-column', 'class-replace!treo-core:views/fields/varchar-column', function (Dep) {

    return Dep.extend({

        searchTypeList: ['contains', 'startsWith', 'equals', 'endsWith', 'like', 'notContains', 'notEquals', 'notLike', 'isEmpty', 'isNotEmpty']

    })
});