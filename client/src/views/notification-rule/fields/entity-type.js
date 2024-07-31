/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/notification-rule/fields/entity-type', 'views/fields/entity-type', function (Dep) {

    return Dep.extend({

        checkAvailability: function (entityType) {
            let defs = this.scopesMetadataDefs[entityType] || {};
            if (!defs.entity || this.getMetadata().get(`scopes.${entityType}.notificationDisabled`)) {
                return false;
            }
            return true
        },
    });
});