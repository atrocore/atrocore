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

Espo.define('views/admin/dynamic-logic/conditions/group-base', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions/group-base',

        data: function () {
            return {
                viewDataList: this.viewDataList,
                operator: this.operator,
                level: this.level,
                groupOperator: this.getGroupOperator(),
                hasAttributes: !!this.getMetadata().get(['scopes', this.scope, 'hasAttribute'])
            };
        },

        events: {
            'click > div.group-head > [data-action="remove"]': function (e) {
                e.stopPropagation();
                this.trigger('remove-item');
            },
            'click > div.group-bottom [data-action="addField"]': function (e) {
                this.actionAddField();
            },
            'click > div.group-bottom [data-action="addAttribute"]': function (e) {
                this.actionAddAttribute();
            },
            'click > div.group-bottom [data-action="addAnd"]': function (e) {
                this.actionAddGroup('and');
            },
            'click > div.group-bottom [data-action="addOr"]': function (e) {
                this.actionAddGroup('or');
            },
            'click > div.group-bottom [data-action="addNot"]': function (e) {
                this.actionAddGroup('not');
            },
            'click > div.group-bottom [data-action="addCurrentUser"]': function (e) {
                this.actionAddCurrentUser();
            }
        },

        setup: function () {
            this.level = this.options.level || 0;
            this.number = this.options.number || 0;
            this.scope = this.options.scope;

            this.itemData = this.options.itemData || {};
            this.viewList = [];

            var conditionList = this.conditionList = this.itemData.value || [];

            this.viewDataList = [];

            conditionList.forEach(function (item, i) {
                var key = this.getKey(i);

                this.createItemView(i, key, item);
                this.addViewDataListItem(i, key);
            }, this);
        },

        getGroupOperator: function () {
            if (this.operator === 'or') return 'or';

            return 'and';
        },

        getKey: function (i) {
            return 'view-' + this.level.toString() + '-' + this.number.toString() + '-' + i.toString();
        },

        createItemView: function (number, key, item) {
            this.viewList.push(key);

            item = item || {};

            var additionalData = item.data || {};

            var type = additionalData.type || item.type || 'equals';
            var field = additionalData.field || item.attribute;
            const attributeId = additionalData.attributeId || item.attributeId;

            var viewName;
            var fieldType;
            if (~['and', 'or', 'not'].indexOf(type)) {
                viewName = 'views/admin/dynamic-logic/conditions/' + type;
            } else {
                if (additionalData.field === '__currentUser') {
                    fieldType = 'link';
                } else {
                    fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
                    if (field === 'id') {
                        fieldType = 'id';
                    }
                }

                if (fieldType) {
                    viewName = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', fieldType, 'view']);
                }
            }

            if (!viewName) return;

            this.createView(key, viewName, {
                itemData: item,
                scope: this.scope,
                level: this.level + 1,
                el: this.getSelector() + ' [data-view-key="' + key + '"]',
                number: number,
                type: type,
                field: field,
                attributeId: attributeId,
                fieldType: fieldType,
                hasFieldLocking: false
            }, function (view) {
                if (this.isRendered()) {
                    view.render()
                }

                this.controlAddItemVisibility();

                this.listenToOnce(view, 'remove-item', function () {
                    this.removeItem(number);
                }, this);
            }, this);
        },

        fetch: function () {
            var list = [];

            this.viewDataList.forEach(function (item) {
                var view = this.getView(item.key);
                const data = view.fetch()
                const attributeId = this.getMetadata().get(['entityDefs', this.scope, 'fields', data.data?.field || data.attribute, 'attributeId'])
                if (attributeId) {
                    data.attributeId = attributeId
                }
                list.push(data);
            }, this);

            return {
                type: this.operator,
                value: list
            };
        },

        removeItem: function (number) {
            var key = this.getKey(number);
            this.clearView(key);

            this.$el.find('[data-view-key="' + key + '"]').remove();
            this.$el.find('[data-view-ref-key="' + key + '"]').remove();

            var index = -1;
            this.viewDataList.forEach(function (data, i) {
                if (data.index === number) {
                    index = i;
                }
            }, this);
            if (~index) {
                this.viewDataList.splice(index, 1);
            }

            this.controlAddItemVisibility();
        },

        actionAddField: function () {
            this.createView('modal', 'views/admin/dynamic-logic/modals/add-field', {
                scope: this.scope
            }, function (view) {
                view.render();

                this.listenToOnce(view, 'add-field', function (field) {
                    this.addField(field);
                    view.close();
                }, this);
            }, this);
        },

        actionAddAttribute: function () {
            const viewName = this.getMetadata().get(['clientDefs', 'Attribute', 'modalViews', 'select']) || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: 'Attribute',
                multiple: false,
                createButton: false,
                massRelateEnabled: false,
                boolFilterList: ['onlyForEntity'],
                boolFilterData: {
                    onlyForEntity: this.scope
                },
                allowSelectAllResult: false,
            }, dialog => {
                dialog.render();

                this.notify(false);
                dialog.once('select', model => {
                    this.notify('Loading...');
                    this.ajaxGetRequest('Attribute/action/attributesDefs', {
                        entityName: this.scope,
                        attributesIds: [model.id]
                    }, { async: false }).success(res => {
                        $.each(res, (field, fieldDefs) => {
                            this.getMetadata().data.entityDefs[this.scope].fields[field] = fieldDefs;
                            this.getLanguage().data[this.scope].fields[field] = fieldDefs.label;

                            if (this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', fieldDefs.type])) {
                                this.addField(field, fieldDefs['attributeId']);
                            }
                        });

                        this.notify(false);

                        this.clearView('dialog');

                    })
                });
            });
        },

        actionAddCurrentUser: function () {
            const field = '__currentUser';
            const type = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', 'link', 'typeList'])[0];

            const i = this.getIndexForNewItem();
            const key = this.getKey(i);

            this.addItemContainer(i);
            this.addViewDataListItem(i, key);

            this.createItemView(i, key, {
                data: {
                    field: field,
                    type: type
                }
            });
        },

        addField: function (field, attributeId) {
            var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
            if (!fieldType && field == 'id') {
                fieldType = 'id';
            }
            if (!this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', fieldType])) {
                throw new Error();
            }

            var type = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', fieldType, 'typeList'])[0];

            var i = this.getIndexForNewItem();
            var key = this.getKey(i);

            this.addItemContainer(i);
            this.addViewDataListItem(i, key);

            this.createItemView(i, key, {
                data: {
                    field: field,
                    type: type,
                    attributeId: attributeId
                }
            });
        },

        getIndexForNewItem: function () {
            if (!this.viewDataList.length) return 0;
            return (this.viewDataList[this.viewDataList.length - 1]).index + 1;
        },

        addViewDataListItem: function (i, key) {
            this.viewDataList.push({
                index: i,
                key: key
            });
        },

        addItemContainer: function (i) {
            var $item = $('<div data-view-key="' + this.getKey(i) + '"></div>');
            this.$el.find('> .item-list').append($item);

            var groupOperatorLabel = this.translate(this.getGroupOperator(), 'logicalOperators', 'Admin');
            var $operatorItem = $('<div class="group-operator" data-view-ref-key="' + this.getKey(i) + '">' + groupOperatorLabel + '</div>');
            this.$el.find('> .item-list').append($operatorItem);
        },

        actionAddGroup: function (operator) {
            var i = this.getIndexForNewItem();
            var key = this.getKey(i);

            this.addItemContainer(i);
            this.addViewDataListItem(i, key);

            this.createItemView(i, key, {
                type: operator,
                value: []
            });
        },

        afterRender: function () {
            this.controlAddItemVisibility();
        },

        controlAddItemVisibility: function () {
        }

    });

});

