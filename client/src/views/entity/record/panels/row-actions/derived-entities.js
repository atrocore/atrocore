/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/record/panels/row-actions/derived-entities', 'views/record/row-actions/relationship-no-unlink', Dep => {

    return Dep.extend({

        getActionList() {
            let list = [];

            Dep.prototype.getActionList.call(this).forEach(item => {
                if (this.model.get('code') !== 'id') {
                    if (item.action !== 'removeRelated' || (item.action === 'removeRelated' && (this.model.get('isCustom')))) {
                        list.push(item);
                    }
                }
            });

            return list;
        }

    });

});
