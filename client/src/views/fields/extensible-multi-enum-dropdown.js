/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/fields/extensible-multi-enum-dropdown', 'views/fields/link-multiple-dropdown', function (Dep) {
    return Dep.extend({
        setup: function () {
            this.idsName = this.name;
            this.namesName = this.name + 'Names';
            this.foreignScope = 'ExtensibleEnumOption';

            Dep.prototype.setup.call(this);
        },

        getExtensibleEnumId() {
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
            if (this.params.extensibleEnumId) {
                extensibleEnumId = this.params.extensibleEnumId;
            }

            if (!extensibleEnumId && this.model.get('attributesDefs')[this.name] && this.model.get('attributesDefs')[this.name]['extensibleEnumId']) {
                extensibleEnumId = this.model.get('attributesDefs')[this.name]['extensibleEnumId'];
            }

            return extensibleEnumId;
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

        prepareOptionsList: function () {
            this.params.options = [];
            this.translatedOptions = {};
            this.params.optionColors = [];

            this.getListOptionsData(this.getExtensibleEnumId()).forEach(option => {
                if (option.id) {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.name || option.id;
                    this.params.optionColors.push(option.color || null);
                }
            })

            if(this.mode === 'edit') {
                let newValues = [];
                (this.model.get(this.idsName) ?? []).forEach((id) => {
                    if((this.params.options ?? []).includes(id)){
                        newValues.push(id);
                    }
                })
                if ((this.model.get(this.idsName) ?? []).length  !== newValues.length) {
                    this.model.set(this.idsName, newValues);
                }
            }
        }
    });
});