/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection-item/fields/name', 'views/fields/varchar',
    Dep => {
        return Dep.extend({
            listLinkTemplate: 'selection-item/fields/list-link',

            data: function () {
                let data = Dep.prototype.data.call(this);
                data['scope'] = this.model.get('entityType');
                data['entityId'] = this.model.get('entityId');
                data['id'] = this.model.get('id');

                return data;
            }
        });
    }
);