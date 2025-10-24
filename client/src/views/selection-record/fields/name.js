/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection-record/fields/name', 'views/fields/varchar',
    Dep => {
        return Dep.extend({
            listLinkTemplate: 'selection-record/fields/list-link',

            data: function () {
                let data = Dep.prototype.data.call(this);
                data['scope'] = this.model.get('entityType');
                data['entityId'] = this.model.get('entityId');

                return data;
            }
        });
    }
);