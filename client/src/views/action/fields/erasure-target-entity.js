/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/erasure-target-entity', 'views/action/fields/target-entity', Dep => {

    return Dep.extend({

        checkAvailability(entityType) {
            const defs = this.scopesMetadataDefs[entityType] || {};

            // personal data can be erased only in the entities that contain it
            if (!defs.containsPersonalData) {
                return false;
            }

            return Dep.prototype.checkAvailability.call(this, entityType);
        }

    });

});
