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

Espo.define('views/fields/entity-fields', ['views/fields/multi-enum', 'views/fields/entity-field'], (Dep, Field) => {

    return Dep.extend({

        entityField: 'entityType',

        setup() {
            this.entityField = this.model.getFieldParam(this.name, 'entityField') || this.entityField;

            this.prepareEnumOptions();
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityType', () => {
                this.model.set(this.name, null);
                this.prepareEnumOptions();
                this.reRender();
            });
        },

        prepareEnumOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            $.each((Field.prototype.getEntityFields.call(this) || []), field => {
                this.params.options.push(field);
                this.translatedOptions[field] = this.translate(field, 'fields', this.getEntityType());
            });
        },

        getEntityType() {
            return Field.prototype.getEntityType.call(this);
        },

    });
});

