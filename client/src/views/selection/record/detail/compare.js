/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare', ['views/record/compare'], function (Dep) {

    return Dep.extend({

        disableModelFetch: true,

        models: [],

        actionList: [],

        selectionModel: null,

        hidePanelNavigation: true,

        setup() {
            this.wait(true);
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.hidePanelNavigation = true;
            Dep.prototype.setup.call(this);

            this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', 'Selection');

            this.listenTo(this.selectionModel, 'overview-filters-changed', () => {
                this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', 'Selection');
                this.reRenderFieldsPanels();
            })

            this.listenToOnce(this, 'after:relationship-panels-render', () => {

                let panelList = this.getRelationshipPanels().map(m => {
                    m.title = m.label;
                    return m;
                });

                panelList = this.getPanelWithFields().concat(panelList);
                this.trigger('detailPanelsLoaded', {list: panelList});
            });

        },

        getModels() {
            return this.models;
        },
    });


});
