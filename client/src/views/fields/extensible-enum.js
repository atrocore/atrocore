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

Espo.define('views/fields/extensible-enum', ['views/fields/link', 'views/fields/colored-enum'], (Dep, ColoredEnum) => {

    return Dep.extend({

        listTemplate: 'fields/extensible-enum/detail',

        detailTemplate: 'fields/extensible-enum/detail',

        selectBoolFilterList: ['onlyForExtensibleEnum', 'onlyAllowedOptions'],

        boolFilterData: {
            onlyForExtensibleEnum() {
                return this.getExtensibleEnumId();
            },
            onlyAllowedOptions() {
                return this.model.getFieldParam(this.name, 'allowedOptions') || this.model.get('allowedOptions') || null
            }
        },

        setup: function () {
            this.idName = this.name;
            this.nameName = this.name + 'Name';
            this.foreignScope = 'ExtensibleEnumOption';

            if (this.options.customBoolFilterData) {
                this.boolFilterData = { ...this.boolFilterData, ...this.options.customBoolFilterData }
            }

            if (this.options.customSelectBoolFilters) {
                this.options.customSelectBoolFilters.forEach(item => {
                    if (!this.selectBoolFilterList.includes(item)) {
                        this.selectBoolFilterList.push(item);
                    }
                });
            }

            Dep.prototype.setup.call(this);
        },

        data() {
            let data = Dep.prototype.data.call(this);

            if (['list', 'detail', 'edit'].includes(this.mode)) {
                const optionData = this.model.get(this.name + 'OptionData') || this.getOptionsData();
                const fontSize = this.model.getFieldParam(this.name, 'fontSize');
                if (optionData.preparedName) {
                    data.nameValue = optionData.preparedName;
                }
                data.description = optionData.description || '';
                data.fontSize = fontSize ? fontSize + 'em' : '100%';
                data.fontWeight = 'normal';
                data.backgroundColor = optionData.color || '#ececec';
                data.color = ColoredEnum.prototype.getFontColor.call(this, data.backgroundColor);
                data.border = ColoredEnum.prototype.getBorder.call(this, data.backgroundColor);
            }

            return data;
        },

        getExtensibleEnumId() {
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']) || this.model.getFieldParam(this.name, 'extensibleEnumId');
            if (this.params.extensibleEnumId) {
                extensibleEnumId = this.params.extensibleEnumId;
            }

            return extensibleEnumId;
        },

        getExtensibleEnumName() {
            const extensibleEnumId = this.getExtensibleEnumId()
            if (!extensibleEnumId) {
                return null
            }
            let key = 'extensible_enum_name_' + extensibleEnumId;

            if (!Espo[key]) {
                this.ajaxGetRequest(`ExtensibleEnum/${extensibleEnumId}`, {}, { async: false }).then(res => {
                    Espo[key] = res['name'];
                });
            }

            return Espo[key];
        },

        getOptionsData() {
            let res = {};

            let id = this.model.get(this.name);
            if (id) {
                this.getListOptionsData(this.getExtensibleEnumId()).forEach(option => {
                    if (option.id === id) {
                        this.model.set(this.nameName, option.name);
                        res = option;
                    }
                });
            }

            return res;
        },

        getCreateAttributes: function () {
            return {
                "extensibleEnumsIds": [this.getExtensibleEnumId()],
                "extensibleEnumsNames": {
                    [this.getExtensibleEnumId()]: this.getExtensibleEnumName()
                }
            }
        },

    });
});

