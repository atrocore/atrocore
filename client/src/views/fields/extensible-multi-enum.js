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

Espo.define('views/fields/extensible-multi-enum', ['treo-core:views/fields/filtered-link-multiple', 'views/fields/colored-enum'], (Dep, ColoredEnum) => {

    return Dep.extend({

        listTemplate: 'fields/extensible-multi-enum/detail',

        detailTemplate: 'fields/extensible-multi-enum/detail',

        selectBoolFilterList: ['onlyForExtensibleEnum'],

        boolFilterData: {
            onlyForExtensibleEnum() {
                let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
                if (this.params.extensibleEnumId) {
                    extensibleEnumId = this.params.extensibleEnumId;
                }

                return extensibleEnumId;
            }
        },

        setup: function () {
            this.nameHashName = this.name + 'Names';
            this.idsName = this.name;

            this.foreignScope = 'ExtensibleEnumOption';

            Dep.prototype.setup.call(this);
        },

        data() {
            let data = Dep.prototype.data.call(this);

            if (['list', 'detail'].includes(this.mode)) {
                const optionsData = this.model.get(this.name + 'OptionsData');
                if (optionsData) {
                    const fontSize = this.model.getFieldParam(this.name, 'fontSize');
                    data.selectedValues = [];
                    optionsData.forEach(option => {
                        let backgroundColor = option.color || '#ececec';
                        data.selectedValues.push({
                            description: option.description || '',
                            fontSize: fontSize ? fontSize + 'em' : '100%',
                            fontWeight: 'normal',
                            backgroundColor: backgroundColor,
                            color: ColoredEnum.prototype.getFontColor.call(this, backgroundColor),
                            border: ColoredEnum.prototype.getBorder.call(this, backgroundColor),
                            optionName: option.name
                        });
                    });
                }
            }

            return data;
        },

    });
});

