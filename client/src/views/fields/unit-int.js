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

Espo.define('views/fields/unit-int', ['views/fields/int', 'views/fields/unit-varchar'], (Dep, Varchar) => {

    return Dep.extend({
        listLinkTemplate: 'fields/varchar/list-link',

        setup() {
            Dep.prototype.setup.call(this);
            Varchar.prototype.prepareOriginalName.call(this);
            Varchar.prototype.afterSetup.call(this);
        },

        init() {
            Varchar.prototype.prepareOptionName.call(this);
            Dep.prototype.init.call(this);
        },

        setMode(mode) {
            Varchar.prototype.setTemplateFromMeasureFormat.call(this,mode);
            Dep.prototype.setMode.call(this, mode)
        },

        isInheritedField: function () {
            return Varchar.prototype.isInheritedField.call(this);
        },

        data() {
            return Varchar.prototype.prepareMeasureData.call(this, this.setDataWithOriginalName());
        },

        getAttributeList() {
            return Varchar.prototype.getAttributeList.call(this)
        },

        setDataWithOriginalName() {
            const data = Dep.prototype.data.call(this);
            const value = isNaN(this.model.get(this.originalName)) ? null : this.model.get(this.originalName);
            data.value = Dep.prototype.formatNumber.call(this, value);

            if (this.model.get(this.originalName) !== null && typeof this.model.get(this.originalName) !== 'undefined') {
                data.isNotEmpty = true;
            }
            data.valueIsSet = this.model.has(this.originalName);

            return data
        },

        validateRequired() {
            return Varchar.prototype.validateRequired.call(this);
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            Varchar.prototype.addMeasureDataOnFetch.call(this, data)
            return data;
        }

    });
});
