/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute-panel/record/panels/attributes', 'views/record/panels/relationship',
    Dep => Dep.extend({

        boolFilterData: {
            onlyForEntity() {
                return this.model.get('entityId');
            }
        },

    })
);