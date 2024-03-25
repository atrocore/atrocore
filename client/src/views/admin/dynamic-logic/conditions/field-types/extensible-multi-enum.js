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

Espo.define('views/admin/dynamic-logic/conditions/field-types/extensible-multi-enum', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        getValueViewName: function () {
            const isDropdown = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dropdown']) ?? false;

            return isDropdown ? 'views/fields/extensible-enum-dropdown' : 'views/fields/extensible-enum';
        },

        createValueViewContains: function () {
            this.createLinkValueField();
        },

        createValueViewNotContains: function () {
            this.createLinkValueField();
        },

        createLinkValueField: function () {
            const ids = this.model.get(this.field);
            const names = this.model.get(this.field + 'Names');
            this.model.set(this.field, Array.isArray(ids) ? ids[0] : ids);
            this.model.set(this.field + 'Name', Array.isArray(names) ? names[0] : names);

            var viewName = this.getValueViewName();
            this.createView('value', viewName, {
                model: this.model,
                name: this.field,
                el: this.getSelector() + ' .value-container',
                mode: 'edit'
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
                attribute: this.field,
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field);

                const values = {};
                values[this.field] = [this.model.get(this.field)];
                values[this.field + 'Names'] = [this.model.get(this.field + 'Name')]
                item.data.values = values;
            }

            return item;
        },

    });

});

