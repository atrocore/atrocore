/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/extensible-enum', ['views/fields/link', 'views/fields/colored-enum'], (Dep, ColoredEnum) => {

    return Dep.extend({

        listTemplate: 'fields/extensible-enum/detail',

        detailTemplate: 'fields/extensible-enum/detail',

        selectBoolFilterList: ['onlyForExtensibleEnum', 'onlyAllowedOptions', 'onlyExtensibleEnumOptionIds'],

        boolFilterData: {
            onlyForExtensibleEnum() {
                return this.getExtensibleEnumId();
            },
            onlyAllowedOptions() {
                return this.model.getFieldParam(this.name, 'allowedOptions') || this.model.get('allowedOptions') || null
            },
            onlyExtensibleEnumOptionIds() {
                return this.model.get(this.idName) ? [this.model.get(this.idName)] : null;
            },
            notDisabledOptions() {
                return this.getDisableOptionsViaConditions();
            }
        },

        sortBy: 'ee_eeo.sorting',

        sortAsc: true,

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

            if (this.getDisableOptionsRules() && !(this.selectBoolFilterList || []).includes('notDisabledOptions')) {
                this.selectBoolFilterList.push('notDisabledOptions');
            }

            Dep.prototype.setup.call(this);
        },

        data() {
            let data = Dep.prototype.data.call(this);

            if (['list', 'detail', 'edit'].includes(this.mode)) {
                let optionData = this.model.get(this.name + 'OptionData')
                if (!optionData || this.model.get(this.name) !== optionData.id) {
                    optionData = this.getOptionsData()
                }
                const fontSize = this.model.getFieldParam(this.name, 'fontSize');
                if (optionData.name || optionData.preparedName) {
                    data.nameValue = optionData.preparedName ?? optionData.name;
                }
                data.description = optionData.description || '';
                data.fontSize = fontSize ? fontSize + 'em' : '100%';
                data.fontWeight = 'normal';
                data.backgroundColor = optionData.color;
                data.color = ColoredEnum.prototype.getFontColor.call(this, data.backgroundColor || '#ececec');
                data.border = ColoredEnum.prototype.getBorder.call(this, data.backgroundColor || '#ececec');
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

