/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/source-entity', 'views/fields/entity-type',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.listenTo(this.model, 'change:applyToPreselectedRecords', () => {
                    this.model.set(this.name, this.model.get('targetEntity'));
                });

                this.listenTo(this.model, 'change:targetEntity', () => {
                    if (this.model.get('applyToPreselectedRecords')) {
                        this.model.set(this.name, this.model.get('targetEntity'));
                    }
                });
            },

            checkAvailability(entityType) {
                let defs = this.scopesMetadataDefs[entityType] || {};
                if (defs.actionDisabled) {
                    return false;
                }

                return Dep.prototype.checkAvailability.call(this, entityType);
            },

        });
    });
