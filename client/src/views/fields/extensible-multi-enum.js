/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/fields/extensible-multi-enum', ['treo-core:views/fields/filtered-link-multiple', 'views/fields/colored-enum'], (Dep, ColoredEnum) => {

    return Dep.extend({

        listTemplate: 'fields/extensible-multi-enum/detail',

        detailTemplate: 'fields/extensible-multi-enum/detail',

        selectBoolFilterList: ['onlyForExtensibleEnum', 'onlyAllowedOptions', 'onlyExtensibleEnumOptionIds'],

        boolFilterData: {
            onlyForExtensibleEnum() {
                return this.getExtensibleEnumId();
            },
            onlyAllowedOptions() {
                return this.model.getFieldParam(this.name, 'allowedOptions') || this.model.get('allowedOptions') || null
            },
            onlyExtensibleEnumOptionIds() {
                return this.model.get(this.idsName) || [];
            }
        },

        setup: function () {
            this.nameHashName = this.name + 'Names';
            this.idsName = this.name;

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

        getBoolFilterData() {
            let data = {};
            this.selectBoolFilterList.forEach(item => {
                if (typeof this.boolFilterData[item] === 'function') {
                    data[item] = this.boolFilterData[item].call(this);
                }
            });
            return data;
        },

        data() {
            let data = Dep.prototype.data.call(this);
            if (['list', 'detail'].includes(this.mode)) {
                const ids = this.model.get(this.name);
                const optionsData = this.model.get(this.name + 'OptionsData') || this.getOptionsData();

                data.selectedValues = [];
                if (ids && ids.length > 0 && optionsData) {
                    const fontSize = this.model.getFieldParam(this.name, 'fontSize');
                    optionsData.forEach(option => {
                        let backgroundColor = option.color;
                        data.selectedValues.push({
                            description: option.description || '',
                            fontSize: fontSize ? fontSize + 'em' : '100%',
                            fontWeight: 'normal',
                            backgroundColor: backgroundColor,
                            color: ColoredEnum.prototype.getFontColor.call(this, backgroundColor || '#ececec'),
                            border: ColoredEnum.prototype.getBorder.call(this, backgroundColor || '#ececec'),
                            optionName: option.preparedName ?? option.name
                        });
                    });
                }
            }

            return data;
        },

        getExtensibleEnumId() {
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']) ?? this.model.getFieldParam(this.name, 'extensibleEnumId');
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


        getCreateAttributes: function () {
            return {
                "extensibleEnumsIds": [this.getExtensibleEnumId()],
                "extensibleEnumsNames": {
                    [this.getExtensibleEnumId()]: this.getExtensibleEnumName()
                }
            }
        },

        getOptionsData() {
            let res = [];

            let ids = this.model.get(this.name);
            if (ids && ids.length > 0) {
                this.getListOptionsData(this.getExtensibleEnumId()).forEach(option => {
                    ids.forEach(id => {
                        if (option.id === id) {
                            res.push(option);
                        }
                    });
                });
            }

            return res;
        },

        fetchSearch: function () {
            let type = this.$el.find('select.search-type').val();
            let data = null;

            if (type === 'anyOf') {
                data = {
                    type: 'arrayAnyOf',
                    value: this.ids || [],
                    nameHash: this.nameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
                if (!data.value.length) {
                    data.value = null;
                }
            } else if (type === 'noneOf') {
                data = {
                    type: 'arrayNoneOf',
                    value: this.ids || [],
                    nameHash: this.nameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
            } else if (type === 'isEmpty') {
                data = {
                    type: 'arrayIsEmpty',
                    data: {
                        type: type
                    }
                };
            } else if (type === 'isNotEmpty') {
                data = {
                    type: 'arrayIsNotEmpty',
                    data: {
                        type: type
                    }
                };
            }

            return data;
        }
    });
});

