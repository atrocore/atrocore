/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/fields/entity', 'views/fields/entity-type', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:type', () => {
                this.model.set('entity', null);
            });
        },

        checkAvailability(entityType) {
            let defs = this.scopesMetadataDefs[entityType] || {};
            if (defs.entity && this.model.urlRoot !== entityType
                && ['Base', 'Hierarchy'].includes(defs.type)
                && !(defs.emHidden)
                && !this.getMetadata().get(['scopes', entityType, 'primaryEntityId'])
            ) {
                return true;
            }
        },

    });
});

