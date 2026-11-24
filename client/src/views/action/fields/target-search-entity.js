/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/target-search-entity', 'views/fields/entity-type',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                // the records are searched for in the target entity
                this.listenTo(this.model, 'change:targetEntity', () => {
                    this.model.set(this.name, this.model.get('targetEntity'));
                });
            },

        });
    });
