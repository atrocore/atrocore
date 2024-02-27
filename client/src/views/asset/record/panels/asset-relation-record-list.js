/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/record/panels/asset-relation-record-list', 'views/record/list',
    Dep => {
        return Dep.extend({
            filterListLayout: function (listLayout) {
                let list    = Dep.prototype.filterListLayout.call(this, listLayout);
                let newList = [];
                
                for (let i = 0; i < list.length; i++) {
                    if (list[i].name === "preview") {
                        continue;
                    }
                    newList.push(list[i]);
                }
                
                return newList;
            }
        });
    }
);