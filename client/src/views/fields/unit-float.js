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
 */

Espo.define('views/fields/unit-float', 'views/fields/float', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.measureId) {
                this.unitFieldName = this.name + 'UnitId';
                this.loadUnitOptions();
                if (this.model.isNew() && this.defaultUnit) {
                    this.model.set(this.unitFieldName, this.defaultUnit);
                }
            }
        },

        init() {
            let fieldName = this.options.name || this.options.defs.name;
            this.options.name = this.getMetadata().get(['entityDefs', this.model.name, 'fields', fieldName, 'mainField']);

            Dep.prototype.init.call(this);
        },

        data() {
            let data = Dep.prototype.data.call(this);

            if (this.measureId) {
                data.unitFieldName = this.unitFieldName;
                data.unitList = this.unitList;
                data.unitListTranslates = this.unitListTranslates;
                data.unitValue = this.model.get(this.unitFieldName);
                data.unitValueTranslate = this.unitListTranslates[data.unitValue] || data.unitValue;
            }

            return data;
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            if (this.measureId) {
                let $unit = this.$el.find(`[name="${this.unitFieldName}"]`);
                data[this.unitFieldName] = $unit ? $unit.val() : null;
            }

            return data;
        },

    });
});

