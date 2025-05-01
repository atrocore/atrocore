/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/search/search-filter-opener', 'view', function (Dep) {
    return Dep.extend({

        getFilterButtonHtml(field) {
            if (this.model.get(field)?.where && Array.isArray(this.model.get(field).where) && this.model.get(field).where.length > 0) {
                return `<i class="ph-fill ph-binoculars" style="color:#06c"></i>`
            } else {
                return `<i class="ph ph-binoculars" ></i>`
            }
        },

        open(foreignScope, initialWhere = [], callback) {
            if (!Array.isArray(initialWhere)) {
                initialWhere = [];
            }
            let getOperator = (type) => {
                let data = {
                    isTrue: 'equal',
                    isFalse: 'equal',
                    like: 'contains',
                    notLike: 'not_contains',
                    startsWith: 'contains',
                    notContains: 'not_contains',
                    equals: 'equal',
                    notEquals: 'not_equal',
                    lessThan: 'less',
                    lessThanOrEquals: 'less_or_equal',
                    greaterThan: 'greater',
                    greaterThanOrEquals: 'greater_or_equal',
                    isNull: 'is_null',
                    isNotNull: 'is_not_null',
                    isLinked: 'in',
                    linkedWith: 'in',
                    notLinkedWith: 'not_in',
                    isNotLinked: 'not_in',
                    arrayAnyOf: 'array_any_of',
                    arrayNoneOf: 'array_none_of',
                    arrayIsEmpty: 'is_null',
                    arrayIsNotEmpty: 'is_not_null',
                }
                return data[type] ?? type;
            }
            let convertToQueryBuilder = (item, queryBuilder) => {
                if (item.attribute && item.type) {
                    if (!['AND', 'OR'].includes(item.type.toUpperCase())) {
                        let id = item.attribute;
                        if (item.isAttribute) {
                            id = 'attr_' + item.attribute;
                        }

                        if (['isLinked', 'isNotLinked'].includes(item.type) && !Array.isArray(item.value)) {
                            item.value = [item.value]
                        }

                        if (['isTrue', 'isFalse'].includes(item.type)) {
                            item.value = item.type === 'isTrue';
                        }

                        queryBuilder.rules.push({
                            id: id,
                            field: id,
                            type: 'boolean',
                            value: item.value,
                            operator: getOperator(item.type),
                            subQuery: item.subQuery
                        });
                    } else {
                        let subQueryBuilder = {
                            condition: item.type.toUpperCase(),
                            rules: [],
                            valid: true
                        }

                        for (const value of item.values) {
                            convertToQueryBuilder(value, subQueryBuilder);
                        }

                        queryBuilder.rules.push(subQueryBuilder);
                    }
                }
            };
            let bool = {};
            let textFilter = "";
            let queryBuilder = {
                condition: "AND",
                rules: [],
                valid: true
            }
            let where = initialWhere || [];
            where.forEach(item => {
                if (item.type === 'bool') {
                    item.value.forEach(v => bool[v] = true);
                }

                if (item.condition) {
                    queryBuilder.rules.push(item);
                }

                if (item.type === 'textFilter') {
                    textFilter = item.value;
                }

                if (item.attribute && item.type) {
                    convertToQueryBuilder(item, queryBuilder);
                }
            });

            if (queryBuilder.rules.length === 1 && queryBuilder.rules[0].condition) {
                queryBuilder = queryBuilder.rules[0];
            }

            if(queryBuilder.rules.length === 0) {
                queryBuilder = {}
            }

            let filters = {bool, queryBuilder, textFilter, queryBuilderApplied: true}

            this.notify(this.translate('loading', 'messages'));
            this.createView('dialog', 'views/search/modals/select-filter-search', {
                scope: foreignScope,
                filters: filters,
                showUnsetSearch: initialWhere.length > 0
            }, (dialog) => {
                dialog.render();
                this.notify(false);

                this.listenTo(dialog, 'select', function (where) {

                    if (!callback) {
                        return;
                    }

                    if (!Array.isArray(where)) {
                        callback({
                            where: null,
                            whereData: null
                        });
                        return ;
                    }

                    let bool = {};
                    textFilter = '';
                    let queryBuilder = {
                        condition: "AND",
                        rules: [],
                        valid: true
                    };

                    where.forEach(item => {
                        if (item.type === 'bool') {
                            item.value.forEach(v => bool[v] = true);
                        }

                        if (item.type === 'textFilter') {
                            textFilter = item.value;
                        }

                        if (item.condition && item.rules) {
                            (item.condition === 'AND' || item.rules.length === 1) ? queryBuilder.rules = queryBuilder.rules.concat(item.rules) : queryBuilder.rules.push(item);
                        }
                    });

                    callback({
                        where: where,
                        whereData: {
                            bool,
                            queryBuilder,
                            textFilter,
                            queryBuilderApplied: true
                        }
                    });

                });
            });
        }
    })
})