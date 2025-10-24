/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/panels/selection-record/compare', 'views/record/compare', function (Dep) {

    return Dep.extend({

        name: 'selectionRecords',

        disableModelFetch: true,

        models: [],

        actionList: [],

        selectionModel: null,

        setup() {
            this.wait(true);
            this.models = [];
            this.selectionModel = this.options.model;
            this.ajaxGetRequest(`selection/${this.model.id}/selectionRecords?select=name,entityType,entityId,entity&collectionOnly=true`, {async: false})
                .then(result => {
                    let entityByScope = {};
                    let order = 0;

                    for (const entityData of result.list) {
                        if (!entityByScope[entityData.entityType]) {
                            entityByScope[entityData.entityType] = [];
                        }
                        entityData.entity._order = order;

                        entityByScope[entityData.entityType].push(entityData.entity);
                        order++
                    }
                    let promises = [];
                    for (const scope in entityByScope) {
                        promises.push(new Promise((resolve) => {
                            this.getModelFactory().create(scope, model => {
                                for (const data of entityByScope[scope]) {
                                    let currentModel = Espo.utils.cloneDeep(model);
                                    currentModel.set(data);
                                    currentModel._order = data._order;
                                    this.models.push(currentModel);
                                }
                                resolve();
                            })
                        }));
                    }

                    Promise.all(promises).then(() => {
                        this.models.sort((a, b) => a._order - b._order);
                        this.model = this.models[0];
                        Dep.prototype.setup.call(this);
                        this.scope = this.model.name;
                        this.wait(false)
                    });
                });

            this.listenToOnce(this, 'after:relationship-panels-render', () => {
                let recordView = this.getParentView().getParentView();
                if (!recordView) {
                    return;
                }
                let panelList = this.getRelationshipPanels().map(m => {
                    m.title = m.label;
                    return m;
                });

                panelList = this.getPanelWithFields().concat(panelList);

                recordView.trigger('detailPanelsLoaded', {list: panelList});
            });


            this.actionList.unshift();
        },

        getModels() {
            return this.models;
        }
    });
});
