/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/stream/notes/composer-update', 'views/stream/note',
    Dep =>  Dep.extend({

        template: 'treo-core:stream/notes/composer-update',

        isEditable: false,

        isRemovable: false,

        messageName: 'composerUpdate',

        events: {
            'click .action[data-action="showUpdateDetails"]': function () {
                this.actionShowUpdateDetails();
            }
        },

        data() {
            let updateData = this.model.get('data');
            let data = Dep.prototype.data.call(this);
            data.fail = !!updateData.status;
            return data;
        },

        setup() {
            this.createMessage();
        },

        actionShowUpdateDetails() {
            this.createView('updateDetailsModal', 'treo-core:views/composer/modals/update-details', {
                output: (this.model.get('data') || {}).output
            }, view => {
                view.render();
            });
        },

    })
);

