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
            this.wait(true);

            this.initPanelList(() => {
                this.wait(false);
            })
        },

        initPanelList(callback) {
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

            this._helper.layoutManager.get(this.model.name, 'insights', null, data => {
                this.layoutData = data
                data.layout.forEach(item => {
                    const panel = panelList.find(p => p.name === item.name)
                    if (panel) {
                        panel.expanded = true;
                        this.panelList.push(panel)
                    }
                })

                this.setupPanelViews();
                callback()
            });
        },

        setPanelTitle(panel) {
            panel.title = this.translate(panel.name, 'insightsPanels', this.scope);
            return panel;
        },

        setEditMode() {
            this.panelList.forEach(p => {
                const panelView = this.getView(p.name);
                if (panelView && typeof panelView.setEditMode === 'function') {
                    panelView.setEditMode();
                }
            });
        },

        setDetailMode() {
            this.panelList.forEach(p => {
                const panelView = this.getView(p.name);
                if (panelView && typeof panelView.setDetailMode === 'function') {
                    panelView.setDetailMode();
                }
            });
        }

    });
});
