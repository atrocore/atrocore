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

        open(foreignScope, initialWhere = [], callback) {
            let getOperator = (type) => {
                let data = {
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
                        if(['isLinked', 'isNotLinked'].includes(item.type) && !Array.isArray(item.value)) {
                            item.value = [item.value]
                        }
                        queryBuilder.rules.push({
                            id: id,
                            field: id,
                            type: 'string',
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

                if (item.attribute && item.type) {
                    convertToQueryBuilder(item, queryBuilder);
                }
            });

            if (queryBuilder.rules.length === 1) {
                queryBuilder = queryBuilder.rules[0];
            }

            let filters = {bool, queryBuilder, queryBuilderApplied: true}
            this.notify(this.translate('loading', 'messages'));
            var viewName = this.getMetadata().get('clientDefs.' + foreignScope + '.modalViews.select') || 'views/modals/select-records';

            this.createView('dialog', viewName, {
                scope: foreignScope,
                createButton: false,
                filters: filters,
                multiple: true,
                massRelateEnabled: true,
            }, (dialog) => {
                dialog.render();
                this.notify(false);

                this.listenTo(dialog, 'select', function (models) {
                    let query = null;
                    if (models.massRelate) {
                        if (models.where.length === 0) {
                            // force subquery if primary filter "all" is used in modal
                            models.where = [{asc: true}]
                        }
                        query = models.where;
                    } else {
                        if (Object.prototype.toString.call(models) !== '[object Array]') {
                            models = [models];
                        }
                        query = [{
                            condition: 'OR',
                            rules: models.map(m => {
                                return {
                                    id: 'id',
                                    field: 'id',
                                    type: 'string',
                                    operator: 'equal',
                                    value: m.id
                                }
                            })
                        }];
                    }

                    if (callback) {
                        callback(query);
                    }
                });
            });
        }
    })
})