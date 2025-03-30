/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/right-side-view-bottom', 'views/record/detail-bottom', function (Dep) {
    return Dep.extend({

        setup: function () {

            this.panelList = this.getMetadata().get(['clientDefs', this.scope,'rightSidePanels']) || []
            this.panelList.forEach(function (item) {
                item.expanded = true;
            })

            this.setupPanelViews();
        }

    });
});
