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
    'views/record/panels/relationship',
    'views/record/list'
], function (Dep, Detail, Relationship, List) {

    return Dep.extend({

        disableModelFetch: true,

        models: [],

        actionList: [],

        selectionModel: null,

        hidePanelNavigation: true,

        itemScope: 'SelectionItem',

        hasReplaceRecord: true,

        hasRemoveRecord: true,

        recordActionView: 'views/record/row-actions/relationship',

        relationName: 'selectionItems',

        actionRemoveItem: function (e) {
            this.afterRemoveButtonClicked(e)
        },

        actionReplaceItem: function (e) {
            this.afterSwapButtonClick(e)
        },

        afterSwapButtonClick(e) {
            let id = $(e.currentTarget).data('id');
            let selectionItemId = $(e.currentTarget).data('selection-item-id');
            let entityType = $(e.currentTarget).data('entity-type');

            if (!id || !entityType || !selectionItemId) {
                return;
            }

            const viewName = this.getMetadata().get(['clientDefs', entityType, 'modalViews', 'select']) || 'views/modals/select-records';
            this.notify('Loading...');
            this.createView('select', viewName, {
                scope: entityType,
                createButton: false,
                multiple: false
            }, (dialog) => {
                dialog.render(() => {
                    this.notify(false);
                });
                dialog.once('select', model => {
                    if (model.id === id) {
                        this.notify(this.translate('notModified', 'messages'));
                        return;
                    }
                    this.notify('Loading...');
                    this.ajaxPatchRequest(`${this.itemScope}/${selectionItemId}`, {
                        entityId: model.id
                    }).then(() => this.getParentView().afterChangedSelectedRecords([model.id]));
                });
            });
        },

        setup() {
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.hidePanelNavigation = true;
            if (!this.selectedFilters) {
                this.selectedFilters = {}
            }
            this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', this.selectionModel.name);

            Dep.prototype.setup.call(this);

            this.listenTo(this, 'selection-item:loaded', models => {
                this.selectionModel.trigger('selection-item:loaded', models);
            })

            this.listenTo(this.selectionModel, 'overview-filters-changed', () => {

                this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', this.selectionModel.name);
                this.listenToOnce(this, 'all-fields-panel-rendered', () => {
                    this.notify(false)
                });
                this.reRenderFieldsPanels();
            })

            this.listenToOnce(this, 'all-panels-rendered', () => {
                this.prepareRelationshipPanels((panelList) => {
                    panelList = this.getPanelWithFields().concat(panelList);
                    this.trigger('detailPanelsLoaded', {list: panelList});
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
                    view.reloadModels(() => view.refreshContent());
                }
            })
        },

        getModel(data, evt) {
            let model = this.getModels().find(m => m.item.id === data.id);
            if (!model) {
                return;
            }
            return model.item;
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

        actionCustomAction(data) {
            this.prepareAndExecuteAction(data, (self) => {
                List.prototype.actionCustomAction.call(self, data);
            })
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
