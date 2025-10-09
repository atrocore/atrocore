/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matching/fields/entity-type', 'views/fields/entity-type', Dep => {

    return Dep.extend({

        checkAvailability(entityType) {
            if (
                Dep.prototype.checkAvailability.call(this, entityType)
                && !this.getMetadata().get(`scopes.${entityType}.matchingDisabled`)
                && ['Base', 'Hierarchy'].includes(this.getMetadata().get(`scopes.${entityType}.type`))
            ) {
                return true;
            }
        },

    });
});