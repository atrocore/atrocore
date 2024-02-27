/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/modals/select-records', "views/modals/select-records", function (Dep) {
    return Dep.extend({

        boolFilterData: {
            onlyActive: function () {
                return true;
            }
        },

        loadSearch() {
            this.boolFilterList = this.boolFilterList.length > 0 ? this.boolFilterList : this.getMetadata().get('clientDefs.' + this.scope + '.selectDefaultFilters.boolFilterList');

            Dep.prototype.loadSearch.call(this);
        }
    });
});