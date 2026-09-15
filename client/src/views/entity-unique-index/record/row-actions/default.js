/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-unique-index/record/row-actions/default', 'views/record/row-actions/relationship', Dep => {

    return Dep.extend({

        getActionList: function () {
            const list = Dep.prototype.getActionList.call(this);

            if (this.model.get('isCustom')) {
                return list;
            }

            return list.filter(item => !['quickEdit', 'removeRelated'].includes(item.action));
        }

    });
});
