/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/search/search-filter-opener', ['view', 'search-manager'], function (Dep, SearchManager) {
    return Dep.extend({

        getFilterButtonHtml(field) {
            if (this.model.get(field)?.where && Array.isArray(this.model.get(field).where) && this.model.get(field).where.length > 0) {
                return `<i class="ph-fill ph-binoculars" style="color:#06c"></i>`
            } else {
                return `<i class="ph ph-binoculars" ></i>`
            }
        },

        open(foreignScope, initialWhere = [], callback, additionalBoolFilterList = [], boolFilterData = {}) {
            let filters = {}

            if (!Array.isArray(initialWhere) && typeof initialWhere === 'object' && initialWhere !== null) {
                filters = initialWhere;
                initialWhere = [];
            } else {
                filters = {
                    bool: [],
                    queryBuilder: {
                        condition: "AND",
                        rules: [],
                        valid: true
                    },
                    textFilter: "",
                    queryBuilderApplied: true
                }
                if (!Array.isArray(initialWhere)) {
                    initialWhere = [];
                }
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
                    linkedWith: 'linked_with',
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
            let where = initialWhere || [];
            where.forEach(item => {
                if (item.type === 'bool') {
                    item.value.forEach(v => bool[v] = true);
                }

                if (item.condition) {
                    filters.queryBuilder.rules.push(item);
                }

                if (item.type === 'textFilter') {
                    filters.textFilter = item.value;
                }

                if (item.attribute && item.type) {
                    convertToQueryBuilder(item, filters.queryBuilder);
                }
            });

            if (filters.queryBuilder.rules && filters.queryBuilder.rules.length === 1 && filters.queryBuilder.rules[0].condition) {
                filters.queryBuilder = filters.queryBuilder.rules[0];
            }

            if (filters.queryBuilder.rules && filters.queryBuilder.rules.length === 0) {
                filters.queryBuilder = {}
                filters.queryBuilderApplied = false;
            }


            this.notify(this.translate('loading', 'messages'));

            let searchManager = new SearchManager(null, null, null, this.getDateTime(), filters || {});

            this.createView('dialog', 'views/search/modals/select-filter-search', {
                scope: foreignScope,
                filters: filters,
                disabledUnsetSearch: !searchManager.isFilterSet() && !searchManager.isTextFilterSet(),
                additionalBoolFilterList: additionalBoolFilterList,
                boolFilterData: boolFilterData,
            }, (dialog) => {
                dialog.render();
                this.notify(false);

                this.listenTo(dialog, 'select', function ({where, whereData}) {

                    if (!callback) {
                        return;
                    }

                    if (!Array.isArray(where)) {
                        callback({
                            where: null,
                            whereData: null
                        });
                        return;
                    }

                    if(whereData) {
                        whereData.boolFilterData = boolFilterData;
                    }

                    callback({
                        where: where,
                        whereData: whereData
                    });
                });
            });
        }
    })
})