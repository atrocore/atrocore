/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/job-manager/actions/show-message', 'views/job-manager/actions/abstract-action',
    Dep => Dep.extend({

        template: 'job-manager/actions/show-message',

        buttonLabel: 'showMessage',

        data() {
            return _.extend({showButton: !!this.actionData.message}, Dep.prototype.data.call(this));
        },

        actionShowMessageModal() {
            this.createView('modal', 'views/job-manager/modals/show-message', {
                message: this.actionData.message
            }, view => view.render());
        }

    })
);

