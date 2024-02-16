/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:search-manager', 'class-replace!treo-core:search-manager', function (SearchManager) {

     _.extend(SearchManager.prototype, {
        getWhere: function () {
            var where = [];

            if (this.data.textFilter && this.data.textFilter != '') {
                where.push({
                    type: 'textFilter',
                    value: this.data.textFilter
                });
            }

            if (this.data.bool) {
                var o = {
                    type: 'bool',
                    value: [],
                    data: {}
                };
                for (var name in this.data.bool) {
                    if (this.data.bool[name]) {
                        o.value.push(name);
                        var boolData = this.data.boolData;
                        if (boolData && boolData[name]) {
                            o.data[name] = boolData[name];
                        }
                    }
                }
                if (o.value.length) {
                    where.push(o);
                }
            }

            if (this.data.primary) {
                var o = {
                    type: 'primary',
                    value: this.data.primary,
                };
                if (o.value.length) {
                    where.push(o);
                }
            }

            if (this.data.queryBuilder && this.storage.get('hasQueryBuilderFilter', this.scope) === 'true') {
                where.push(this.data.queryBuilder);
            } else if (this.data.advanced && this.storage.get('hasQueryBuilderFilter', this.scope) !== 'true') {
                var groups = {};
                for (var name in this.data.advanced) {
                    var defs = this.data.advanced[name];
                    if (!defs) {
                        continue;
                    }
                    var clearedName = name.split('-')[0];
                    var part = this.getWherePart(clearedName, defs);
                    (groups[clearedName] = groups[clearedName] || []).push(part);
                }
                var finalPart = [];
                for (var name in groups) {
                    var group;
                    if (groups[name].length > 1) {
                        group = {
                            type: 'or',
                            value: groups[name]
                        };
                    } else {
                        group = groups[name][0];
                    }
                    finalPart.push(group);
                }
                where = where.concat(finalPart);
            }
            return where;
        },

         getWherePart: function (name, defs) {
             var attribute = name;

             if ('where' in defs) {
                 return defs.where;
             } else {
                 var type = defs.type;

                 if (type == 'or' || type == 'and') {
                     var a = [];
                     var value = defs.value || {};
                     for (var n in value) {
                         a.push(this.getWherePart(n, _.extend({}, value[n], {
                             subQuery: defs.subQuery ?? [],
                             fieldParams: {
                                 isAttribute: defs.isAttribute || (defs.fieldParams || {}).isAttribute
                             }
                         })));
                     }
                     return {
                         type: type,
                         value: a
                     };
                 }
                 if ('field' in defs) { // for backward compatibility
                     attribute = defs.field;
                 }
                 if ('attribute' in defs) {
                     attribute = defs.attribute;
                 }
                 if (defs.dateTime) {
                     return {
                         type: type,
                         attribute: attribute,
                         isAttribute: (defs.fieldParams || {}).isAttribute,
                         subQuery: defs.subQuery ?? [],
                         value: defs.value,
                         dateTime: true,
                         timeZone: this.dateTime.timeZone || 'UTC'
                     };
                 } else {
                     value = defs.value;
                     return {
                         isAttribute: (defs.fieldParams || {}).isAttribute,
                         type: type,
                         attribute: attribute,
                         subQuery: defs.subQuery ?? [],
                         value: value
                     };
                 }
             }
         },
    });

    return SearchManager;
});