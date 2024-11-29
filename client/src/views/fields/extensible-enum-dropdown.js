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
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
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

        getWhereFilter() {
            let boolWhere = {
                type: 'bool',
                value:['onlyForExtensibleEnum'],
                data:{
                    'onlyForExtensibleEnum': this.getExtensibleEnumId()
                }
            };

            if( Array.isArray(this.selectBoolFilterList) && this.selectBoolFilterList.length > 0) {
                boolWhere.value.push(...this.selectBoolFilterList);
            }

            let boolData = this.getBoolFilterData();
            if (boolData && Object.keys(boolData).length > 0) {
                boolWhere.data = {...boolWhere.data, ...boolData}
            }
            return [boolWhere];
        }
    });
});