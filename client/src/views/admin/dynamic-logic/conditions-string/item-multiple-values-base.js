/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

Espo.define('views/admin/dynamic-logic/conditions-string/item-multiple-values-base', 'views/admin/dynamic-logic/conditions-string/item-base', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions-string/item-multiple-values-base',

        data: function () {
            return {
                valueViewDataList: this.valueViewDataList,
                scope: this.scope,
                operator: this.operator,
                operatorString: this.operatorString,
                field: this.field
            };
        },

        populateValues: function () {
        },


        getValueViewKey: function (i) {
            return 'view-' + this.level.toString() + '-' + this.number.toString() + '-' + i.toString();
        },

        createValueFieldView: function () {
            var valueList = this.itemData.value || [];

            var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'type']) || 'base';
            var viewName = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'view']) || this.getFieldManager().getViewName(fieldType);

            if (this.field === '__currentUser') {
                fieldType = 'link'
                viewName = 'views/fields/user-with-avatar'
            }

            this.valueViewDataList = [];
            if (['extensibleMultiEnum','linkMultiple'].includes(fieldType)){
                var model = this.model.clone();
                model.set(this.itemData.attribute, valueList);
                if (this.itemData.data?.field && this.itemData.data?.values?.[this.itemData.data.field + 'Names']) {
                    model.set(this.itemData.data.field + 'Names', this.itemData.data.values[this.itemData.data.field + 'Names']);
                }


                const key = this.getValueViewKey(0);
                this.valueViewDataList.push({
                    key: key,
                    isEnd: true
                });

                this.createView(key, viewName, {
                    model: model,
                    name: this.field,
                    el: this.getSelector() + ' [data-view-key="' + key + '"]'
                });
                return
            }

            valueList.forEach(function (value, i) {
                var model = this.model.clone();
                model.set(this.itemData.attribute, value);
                if (this.itemData.data?.field && this.itemData.data?.values?.[this.itemData.data.field + 'Names']) {
                    model.set(this.itemData.data.field + 'Name', this.itemData.data.values[this.itemData.data.field + 'Names'][value]);
                }

                var key = this.getValueViewKey(i);
                this.valueViewDataList.push({
                    key: key,
                    isEnd: i === valueList.length - 1
                });

                this.createView(key, viewName, {
                    model: model,
                    name: this.field,
                    el: this.getSelector() + ' [data-view-key="' + key + '"]',
                    inlineEditDisabled: true,
                    disableConditions: true
                });
            }, this);
        },

    });

});

