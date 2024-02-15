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

Espo.define('views/fields/extensible-enum', ['views/fields/link', 'views/fields/colored-enum'], (Dep, ColoredEnum) => {

    return Dep.extend({

        listTemplate: 'fields/extensible-enum/detail',

        detailTemplate: 'fields/extensible-enum/detail',

        selectBoolFilterList: ['onlyForExtensibleEnum'],

        boolFilterData: {
            onlyForExtensibleEnum() {
                return this.getExtensibleEnumId();
            }
        },

        setup: function () {
            this.idName = this.name;
            this.nameName = this.name + 'Name';
            this.foreignScope = 'ExtensibleEnumOption';

            Dep.prototype.setup.call(this);
        },

        data() {
            let data = Dep.prototype.data.call(this);

            if (['list', 'detail'].includes(this.mode)) {
                const optionData = this.model.get(this.name + 'OptionData') || this.getOptionsData();
                const fontSize = this.model.getFieldParam(this.name, 'fontSize');

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
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
            if (this.params.extensibleEnumId) {
                extensibleEnumId = this.params.extensibleEnumId;
            }

            return extensibleEnumId;
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

        createQueryBuilderFilter() {
            const scope = this.model.urlRoot;

            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', scope),
                type: 'string',
                operators: [
                    'in',
                    'not_in',
                    'is_null',
                    'is_not_null'
                ],
                input: (rule, inputName) => {
                    if (!rule || !inputName) {
                        return '';
                    }

                    const attribute = this.defs.params.attribute ?? null;

                    this.filterValue = null;
                    this.getModelFactory().create(null, model => {
                        this.createView(inputName, 'views/fields/extensible-multi-enum', {
                            name: 'value',
                            el: `#${rule.id} .field-container`,
                            model: model,
                            mode: 'edit',
                            defs: {
                                name: 'value',
                                params: {
                                    extensibleEnumId: attribute ? attribute.get('extensibleEnumId') : this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'extensibleEnumId'])
                                }
                            },
                        }, view => {
                            this.listenTo(view, 'change', () => {
                                this.filterValue = model.get('value');
                                rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                            });
                            this.renderAfterEl(view, `#${rule.id} .field-container`);
                        });
                        this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                            model.set('value', rule.value);
                        });
                    });
                    return `<div class="field-container"></div><input type="hidden" name="${inputName}" />`;
                },
                valueGetter: this.filterValueGetter.bind(this)
            };
        },

    });
});

