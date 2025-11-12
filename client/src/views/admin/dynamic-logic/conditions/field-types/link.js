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

Espo.define('views/admin/dynamic-logic/conditions/field-types/link', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field + 'Id',
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                if (['in', 'notIn', 'inTeams', 'notInTeams'].includes(item.type)) {
                    let field = this.field
                    if (['inTeams', 'notInTeams'].includes(item.type)) {
                        field = this.field + 'Teams'
                    }

                    item.value = this.model.get(field + 'Ids');

                    const values = {};
                    values[field + 'Ids'] = this.model.get(field + 'Ids');
                    values[field + 'Names'] = this.model.get(field + 'Names');
                    item.data.values = values;
                } else {
                    item.value = this.model.get(this.field + 'Id');

                    const values = {};
                    values[this.field + 'Name'] = this.model.get(this.field + 'Name');
                    item.data.values = values;
                }
            }

            return item;
        },

        createValueViewIn: function () {
            this.createLinkMultipleValueField();
        },

        createValueViewNotIn: function () {
            this.createLinkMultipleValueField();
        },

        createValueViewInTeams: function () {
            this.createLinkMultipleValueField('Team', this.field + 'Teams');
        },

        createValueViewNotInTeams: function () {
            this.createLinkMultipleValueField('Team', this.field + 'Teams');
        },

        createLinkMultipleValueField: function (foreignScope = null, field = null) {
            foreignScope = foreignScope || this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'entity']) || this.getMetadata().get(['entityDefs', this.scope, 'links', this.field, 'entity'])
            field = field || this.field

            if (!this.model.get(field + 'Ids')) {
                const id = this.model.get(field + 'Id');
                const name = this.model.get(field + 'Name');
                if (id) {
                    this.model.set(field + 'Ids', [id]);
                }
                if (name) {
                    this.model.set(field + 'Names', { [id]: name });
                }
            }

            const viewName = 'views/fields/link-multiple';

            this.createView('value', viewName, {
                model: this.model,
                name: field,
                el: this.getSelector() + ' .value-container',
                mode: 'edit',
                readOnlyDisabled: true,
                disableConditions: true,
                foreignScope: foreignScope
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

    });

});

