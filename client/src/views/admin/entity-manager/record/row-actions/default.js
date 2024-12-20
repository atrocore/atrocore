/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/record/row-actions/default', 'views/record/row-actions/default', Dep => {

    return Dep.extend({

        getActionList() {
            let list = [];

            Dep.prototype.getActionList.call(this).forEach(item => {
                if (item.action !== 'quickRemove' || (item.action === 'quickRemove' && this.model.get('isCustom'))) {
                    list.push(item);
                }
            });

            return list;
        }

    });

});
