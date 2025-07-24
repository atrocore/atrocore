/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/fields/extensible-enum-dropdown', 'views/fields/link-dropdown', function (Dep) {
    return Dep.extend({

        setup: function () {
            this.idName = this.name;
            this.nameName = this.name + 'Name';
            this.foreignScope = 'ExtensibleEnumOption';

            Dep.prototype.setup.call(this);
        },

        getExtensibleEnumId() {
            let extensibleEnumId = this.model.getFieldParam(this.name, 'extensibleEnumId') || this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
            if (this.params.extensibleEnumId) {
                extensibleEnumId = this.params.extensibleEnumId;
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
            this.params.optionColors = {};

            let allowedOptions = this.model.getFieldParam(this.name, 'allowedOptions') || [];

            this.getListOptionsData(this.getExtensibleEnumId()).forEach(option => {
                if (option.id && (allowedOptions.length === 0 || allowedOptions.includes(option.id))) {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.preparedName || option.name || option.id;
                    this.params.optionColors[option.id] = option.color || null;
                }
            })
        }
    });
});