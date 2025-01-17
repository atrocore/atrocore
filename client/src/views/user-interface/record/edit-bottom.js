/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-interface/record/edit-bottom', 'views/record/edit-bottom', Dep => {

    return Dep.extend({

        relationshipPanels: true,

        setupRelationshipPanels() {

            if (this.model.id === '1') {
                this.relationshipsLayout = [
                    {
                        "name": "backgrounds",
                        "view": "views/user-interface/record/panels/backgrounds",
                        "canClose": false
                    }
                ];
            }
            Dep.prototype.setupRelationshipPanels.call(this);
        },

    });

});