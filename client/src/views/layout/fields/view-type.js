/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/view-type', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            if (!this.params.translation) {
                this.params.translation = 'Admin.layouts';
            }
            this.listenTo(this.model, 'change:entity', () => {
                if (this.getMetadata().get(['clientDefs', this.model.get('entity'), 'kanbanViewMode'])) {
                    this.resetOptionList()
                } else {
                    this.disableOptions(['kanban'])
                }
            })
            Dep.prototype.setup.call(this);
        },
    });
});

