/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/actions/show-message', 'treo-core:views/queue-manager/actions/abstract-action',
    Dep => Dep.extend({

        template: 'treo-core:queue-manager/actions/show-message',

        buttonLabel: 'showMessage',

        data() {
            return _.extend({
                showButton: !!this.actionData.message
            }, Dep.prototype.data.call(this));
        },

        actionShowMessageModal() {
            this.createView('modal', 'treo-core:views/queue-manager/modals/show-message', {
                message: this.actionData.message
            }, view => view.render());
        }

    })
);

