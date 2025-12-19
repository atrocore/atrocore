/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/match-master-records', 'views/entity/fields/match-duplicates',
    Dep => Dep.extend({

        getLinkUrl() {
            return `/#Matching/view/${this.model.id}-S2M`;
        },

    })
);

