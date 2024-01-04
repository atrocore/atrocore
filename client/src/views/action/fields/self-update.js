/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/self-update', 'views/fields/bool',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:selfUpdate', () => {
                if (this.model.get('selfUpdate') && this.model.get('workflowId')) {
                    this.ajaxGetRequest(`Workflow/${this.model.get('workflowId')}`).success(res => {
                        this.model.set('entityType', res.entityType);
                    });
                }
            });
        },

    })
);
