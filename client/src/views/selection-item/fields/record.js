/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection-item/fields/record', 'views/fields/link',
    Dep => {
        return Dep.extend({

            createDisabled: true,

            selectBoolFilterList: ['notEntity'],

            boolFilterData: {
                notEntity() {
                    return this.model.get('recordId');
                }
            },

            setup: function () {
                this.name = 'record'
                this.foreignScope = this.model.get('entityName')

                Dep.prototype.setup.call(this);

                this.listenTo(this.model, 'change:entityName', () => {
                    this.foreignScope = this.model.get('entityName')
                });

                this.listenTo(this.model, 'change:recordId', () => {
                   this.model.get('entityId', this.model.get('recordId'))
                });
            }
        });

    });