

Espo.define('treo-core:views/fields/varchar', 'class-replace!treo-core:views/fields/varchar', function (Dep) {

    return Dep.extend({

        searchTypeList: ['contains', 'startsWith', 'equals', 'endsWith', 'like', 'notContains', 'notEquals', 'notLike', 'isEmpty', 'isNotEmpty']

    })
});