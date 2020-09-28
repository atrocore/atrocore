

Espo.define('treo-core:views/fields/text', 'class-replace!treo-core:views/fields/text', function (Dep) {

    return Dep.extend({

        searchTypeList: ['contains', 'startsWith', 'equals', 'endsWith', 'like', 'notContains', 'notEquals', 'notLike', 'isEmpty', 'isNotEmpty']

    })
});