/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/primary-entity', 'views/fields/link',
    Dep => Dep.extend({

        createDisabled: true,

        // the primary entity has the same type, the link points to the subtype of this type
        selectBoolFilterList: ['onlyForDerivativeEnabled'],

    })
);

