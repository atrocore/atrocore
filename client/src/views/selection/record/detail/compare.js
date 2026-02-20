/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare', [
    'views/record/compare',
    'views/record/detail',
    'views/record/panels/relationship'
], function (Dep, Detail, Relationship) {

    return Dep.extend({

        disableModelFetch: true,

        models: [],

        actionList: [],

        selectionModel: null,

        hidePanelNavigation: true,

        itemScope: 'SelectionItem',

        recordActionView: 'views/record/row-actions/relationship',

        relationName: 'selectionItems',

        setup() {
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.hidePanelNavigation = true;
            this.selectedFilters = this.getStorage().get('fieldFilter', this.selectionModel.name) || [];

            Dep.prototype.setup.call(this);

            this.listenTo(this, 'selection-item:loaded', models => {
                this.selectionModel.trigger('selection-item:loaded', models);
            })

            this.listenTo(this.selectionModel, 'overview-filters-changed', () => {
                this.selectedFilters = this.getStorage().get('fieldFilter', this.selectionModel.name);
                this.listenToOnce(this, 'all-fields-panel-rendered', () => {
                    this.notify(false)
                });
                this.reRenderFieldsPanels();
            })

            this.listenToOnce(this, 'all-panels-rendered', () => {
                this.prepareRelationshipPanels((panelList) => {
                    panelList = this.getPanelWithFields().concat(panelList);
                    this.trigger('detailPanelsLoaded', { list: panelList });
                    this.getParentView().setupLayoutEditorButton();
                });
            });

            this.getModels().forEach(model => {
                this.createView(model.id + 'Action', this.recordActionView, {
                    el: this.options.el + ` [data-id="${model.id}"] .inline-actions`,
                    model: model.item,
                    scope: this.itemScope,
                    showIcons: true,
                    parentModelName: this.selectionModel.name,
                    relationName: this.relationName
                })
            });

            this.listenTo(this, 'refresh', () => {
                let view = this.getParentView();
                if (view) {
                    view.refresh();
                }
            });
        },

        prepareAndExecuteAction(data, callback) {
            let model = this.getModels().find(m => m.item.id === data.id);
            if (!model) {
                return;
            }
            let itemModel = model.item;
            let self = Espo.utils.clone(this);
            self.model = this.selectionModel;
            self.link = this.relationName;
            self.getModel = () => {
                let model = this.getModels().find(m => m.item.id === data.id);
                if (!model) {
                    return;
                }
                return model.item;
            }
            this.getCollectionFactory().create(this.itemScope, (collection) => {
                self.collection = collection;
                self.collection.add(itemModel);
                self.collection.url = this.selectionModel.name + '/' + this.selectionModel.id + '/' + this.relationName;
                callback(self);
            });
        },

        actionUnlinkRelated(data) {
            this.prepareAndExecuteAction(data, (self) => {
                Relationship.prototype.actionUnlinkRelated.call(self, data);
            });
        },

        actionRemoveRelated(data) {
            this.prepareAndExecuteAction(data, (self) => {
                self.isHierarchical = () => false;
                Relationship.prototype.actionRemoveRelated.call(self, data);
            });
        },

        actionUniversalAction(data) {
            this.prepareAndExecuteAction(data, (self) => {
                Relationship.prototype.actionUniversalAction.call(self, data);
            });
        },

        executeAction: function (action, data = null, e = null) {
            var method = 'action' + Espo.Utils.upperCaseFirst(action);
            if (typeof this[method] == 'function') {
                this[method].call(this, data, e);
            }
        },

        actionDelete() {
            const self = Espo.Utils.clone(this);
            self.model = this.selectionModel;
            self.isHierarchical = () => false;
            self.delete = Detail.prototype.delete.bind(self);
            self.exit = Detail.prototype.exit.bind(self);

            self.delete();
        },

        getModels() {
            return this.models;
        },

        getRecordButtons() {
            return this.getParentView().getCompareButtons();
        },

        isComparisonAcrossScopes() {
            return this.selectionModel.get('type') !== 'single' && this.selectionModel.get('entityTypes').length > 1;
        },

        canLoadActivities() {
            return true;
        },

        getSvelteSideViewProps(parentView) {
            let thisClone = Espo.utils.clone(this);

            thisClone.scope = 'Selection';
            thisClone.model = this.selectionModel;
            thisClone.mode = 'detail';

            let option = Detail.prototype.getSvelteSideViewProps.call(thisClone, parentView);

            option.showInsights = true;
            option.isCollapsed = false;

            return option;
        }
    });
});
