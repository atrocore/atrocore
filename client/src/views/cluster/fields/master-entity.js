/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/cluster/fields/master-entity', 'views/fields/entity-type', Dep => {

    return Dep.extend({

        checkAvailability(entityType) {
            let defs = this.scopesMetadataDefs[entityType] || {};
            if (defs.entity && this.model.urlRoot !== entityType && ['Base', 'Hierarchy'].includes(defs.type) && !(defs.emHidden)) {
                return true;
            }
        },

    });
});

