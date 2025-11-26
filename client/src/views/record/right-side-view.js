/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/right-side-view', 'views/record/detail-bottom', function (Dep) {
    return Dep.extend({

        setup: function () {
            let panelList = [
                {
                    name: 'summary',
                    view: 'views/record/right-side-view-panel',
                },
                {
                    name: 'accessManagement',
                    view: 'views/record/right-side-view-panel'
                }
            ];

            panelList.push(...this.getMetadata().get(['clientDefs', this.scope, 'rightSidePanels']) || []);
            panelList = panelList.filter(p => {
                if (p.aclScope) {
                    return this.getAcl().check(p.aclScope, 'read');
                }
                return true;
            });

            this.panelList = []

            this.wait(true);

            this._helper.layoutManager.get(this.model.name, 'insights', null, function (data) {
                this.layoutData = data
                data.layout.forEach(item => {
                    const panel = panelList.find(p => p.name === item.name)
                    if (panel) {
                        this.panelList.push(panel)
                    }
                })

                this.setupPanelViews();
                this.wait(false);
            }.bind(this));

        }

    });
});
