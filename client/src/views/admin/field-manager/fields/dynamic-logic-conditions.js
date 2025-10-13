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

Espo.define('views/admin/field-manager/fields/dynamic-logic-conditions', 'views/fields/base', function (Dep) {

    return Dep.extend({

        listTemplate: 'admin/field-manager/fields/dynamic-logic-conditions/detail',

        detailTemplate: 'admin/field-manager/fields/dynamic-logic-conditions/detail',

        editTemplate: 'admin/field-manager/fields/dynamic-logic-conditions/edit',

        events: {
            'click [data-action="editConditions"]': function () {
                this.edit();
            }
        },

        data: function () {
        },

        setup: function () {
            this.conditionGroup = Espo.Utils.cloneDeep((this.model.get(this.name) || {}).conditionGroup || []);
            this.scope = this.params.scope || this.options.scope;

            // load attributes in entityDefs
            if (this.getMetadata().get(['scopes', this.scope, 'hasAttribute'])) {
                this.loadAttributesInMetadata(this.getAttributeIds(this.conditionGroup))
            }
            this.createStringView();
        },

        loadAttributesInMetadata(ids){
            if (ids.length > 0) {
                const key = 'attributesDefs-' + this.scope + '-' + ids.join(',');
                if (!Espo[key]) {
                    this.wait(true);
                    this.ajaxGetRequest('Attribute/action/attributesDefs', {
                        entityName: this.scope,
                        attributesIds: ids
                    }, { async: false }).success(res => {
                        $.each(res, (field, fieldDefs) => {
                            this.getMetadata().data.entityDefs[this.scope].fields[field] = fieldDefs;
                            this.getLanguage().data[this.scope].fields[field] = fieldDefs.label;
                        });

                        Espo[key] = true;
                        this.wait(false);
                    })
                }
            }
        },

        getAttributeIds(conditionGroup) {
            var ids = [];
            for (var i = 0; i < conditionGroup.length; i++) {
                var condition = conditionGroup[i];
                if (condition.attribute) {
                    if (condition.attributeId && ids.indexOf(condition.attributeId) === -1) {
                        ids.push(condition.attributeId);
                    }
                } else if (condition.value && condition.value.length > 0) {
                    ids = ids.concat(this.getAttributeIds(condition.value));
                }
            }

            return ids
        },

        createStringView: function () {
            this.createView('conditionGroup', 'views/admin/dynamic-logic/conditions-string/group-base', {
                el: this.getSelector() + ' .top-group-string-container',
                itemData: {
                    value: this.conditionGroup
                },
                operator: 'and',
                scope: this.scope
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        edit: function () {
            this.createView('modal', 'views/admin/dynamic-logic/modals/edit', {
                conditionGroup: this.conditionGroup,
                scope: this.scope
            }, function (view) {
                view.render();

                this.listenTo(view, 'apply', function (conditionGroup) {
                    this.conditionGroup = conditionGroup;
                    this.model.trigger(`apply:${this.name}`);
                    this.createStringView();
                }, this);
            }, this);
        },

        fetch: function () {
            var data = {};
            data[this.name] = {
                conditionGroup: this.conditionGroup
            };

            if (this.conditionGroup.length === 0) {
                data[this.name] = null;
            }

            return data;
        }
    });

});
