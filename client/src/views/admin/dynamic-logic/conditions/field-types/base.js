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

Espo.define('views/admin/dynamic-logic/conditions/field-types/base', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions/field-types/base',

        data: function () {
            return {
                type: this.type,
                field: this.field,
                scope: this.scope,
                typeList: this.typeList
            };
        },

        events: {
            'click > div > div > [data-action="remove"]': function (e) {
                e.stopPropagation();
                this.trigger('remove-item');
            }
        },

        setup: function () {
            this.type = this.options.type;
            this.field = this.options.field;
            this.scope = this.options.scope;
            this.fieldType = this.options.fieldType;
            this.attributeId = this.options.attributeId;

            this.itemData = this.options.itemData;
            this.additionalData = (this.itemData.data || {});

            this.typeList = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', this.fieldType, 'typeList']);
            if (this.attributeId) {
                this.typeList = ['isLinked', 'isNotLinked', ...this.typeList];
            }
            if (this.fieldType === 'link') {
                const foreignScope = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'entity']) || this.getMetadata().get(['entityDefs', this.scope, 'links', this.field, 'entity']);
                if (foreignScope === 'User' || this.field === '__currentUser') {
                    this.typeList = [...this.typeList, 'inTeams', 'notInTeams'];
                }
            }

            this.wait(true);
            this.getModelFactory().create(this.scope, function (model) {
                model.defs.fields.__currentUser = {
                    type: "link",
                    view: "views/fields/user-with-avatar"
                };
                model.defs.links.__currentUser = {
                    entity: "User",
                    type: "belongsTo"
                };

                this.model = model;
                this.populateValues();

                this.manageValue();
                this.wait(false);
            }, this);
        },

        afterRender: function () {
            this.$type = this.$el.find('select[data-name="type"]');
            this.$type.on('change', function () {
                this.type = this.$type.val();
                this.manageValue();
            }.bind(this));
        },

        populateValues: function () {
            if (this.itemData.attribute) {
                this.model.set(this.itemData.attribute, this.itemData.value);
            }
            this.model.set(this.additionalData.values || {});
        },

        getValueViewName: function () {
            let fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'type'])
                || this.model.getFieldParam(this.field, 'type') || 'base';

            fieldType = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', fieldType, 'valueType']) || fieldType;
            return this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'view']) || this.getFieldManager().getViewName(fieldType);
        },

        getValueFieldName: function () {
            return this.field;
        },

        manageValue: function () {
            var valueType = this.getMetadata().get(['clientDefs', 'DynamicLogic', 'conditionTypes', this.type, 'valueType']);
            let defaultFieldCreateValueView = () => {
                var viewName = this.getValueViewName();
                var fieldName = this.getValueFieldName();
                this.createView('value', viewName, {
                    model: this.model,
                    name: fieldName,
                    el: this.getSelector() + ' .value-container',
                    mode: 'edit',
                    readOnlyDisabled: true,
                    disableConditions: true,
                    hasFieldLocking: false
                }, function (view) {
                    if (this.isRendered()) {
                        view.render();
                    }
                }, this);
            }

            if (valueType === 'custom') {
                this.clearView('value');
                var methodName = 'createValueView' + Espo.Utils.upperCaseFirst(this.type);
                if (this[methodName]) {
                    this[methodName]();
                } else {
                    defaultFieldCreateValueView();
                }
            } else if (valueType === 'field') {
                defaultFieldCreateValueView()
            } else {
                this.clearView('value');
            }
        },

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field,

            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field);
            }

            return item;
        }

    });

});

