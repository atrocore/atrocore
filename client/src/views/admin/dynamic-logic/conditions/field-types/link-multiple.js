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

Espo.define('views/admin/dynamic-logic/conditions/field-types/link-multiple', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        getValueFieldName: function () {
            return this.name;
        },

        getValueViewName: function () {
            return 'views/fields/link';
        },

        createValueViewContains: function () {
            this.createLinkValueField();
        },

        createValueViewNotContains: function () {
            this.createLinkValueField();
        },

        createLinkValueField: function () {
            const ids = this.model.get(this.field + 'Ids');
            const names = this.model.get(this.field + 'Names');
            const id = Array.isArray(ids) ? ids[0] : ids;
            if (ids) {
                this.model.set(this.field + 'Id', id);
            }

            if (typeof names === 'object') {
                this.model.set(this.field + 'Name', names[id] ?? id);
            }

            const viewName = 'views/fields/link';

            this.createView('value', viewName, {
                model: this.model,
                name: this.field,
                el: this.getSelector() + ' .value-container',
                mode: 'edit',
                readOnlyDisabled: true,
                foreignScope: this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'entity']) || this.getMetadata().get(['entityDefs', this.scope, 'links', this.field, 'entity'])
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field + 'Ids',
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field + 'Id');

                const values = {};
                const names = {};
                names[this.model.get(this.field + 'Id')] = this.model.get(this.field + 'Name');
                values[this.field + 'Names'] = names;
                values[this.field + 'Ids'] = [this.model.get(this.field + 'Id')];
                item.data.values = values;
            }

            return item;
        }

    });

});

