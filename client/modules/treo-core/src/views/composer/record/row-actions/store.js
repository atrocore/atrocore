/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/record/row-actions/store', 'views/record/row-actions/default',
    Dep => Dep.extend({

        disableActions: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model.collection, 'disableActions', (disableActions) => {
                this.disableActions = disableActions;
                this.reRender();
            });
        },

        getActionList() {
            let list = [];
            let versions = this.model.get('versions');
            if (!this.disableActions && versions && versions.length && this.model.get('status') === 'available') {
                list.push({
                    action: 'installModule',
                    label: 'installModule',
                    data: {
                        id: this.model.id
                    }
                });
            }
            return list;
        },

    })
);
