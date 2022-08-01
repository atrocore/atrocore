/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
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

            if (this.data.advanced) {
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