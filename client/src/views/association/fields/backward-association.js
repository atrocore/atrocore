/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/association/fields/backward-association', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyActive', 'onlyForEntity'],

        boolFilterData: {
            onlyForEntity() {
                return this.model.get('entityId') || null;
            }
        },
    })
);
