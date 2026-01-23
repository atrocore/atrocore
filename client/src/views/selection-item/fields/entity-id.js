/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection-item/fields/entity-id', 'views/fields/link',
    Dep => {
        return Dep.extend({

            createDisabled: true,

            selectBoolFilterList: ['notEntity'],

            boolFilterData: {
                notEntity() {
                    return this.model.get('entityId');
                }
            },

            setup: function () {
                this.name = 'entity'
                this.foreignScope = this.model.get('entityType')
                this.model.set('entityName', this.model.get('name'));

                Dep.prototype.setup.call(this);

                this.listenTo(this.model, 'change:entityType', () => {
                    this.foreignScope = this.model.get('entityType')
                })
            },

            getConditions(type) {
                return this.getMetadata().get(`entityDefs.${this.model.name}.fields.entityId.conditionalProperties.${type}.conditionGroup`);
            }
        });

    });