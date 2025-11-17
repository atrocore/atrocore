/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/detail', ['views/detail', 'model', 'views/record/list'], function (Dep, Model, List) {

    return Dep.extend({

        selectionViewMode: 'standard',

        availableModes: ['standard', 'compare', 'merge'],

        hidePanelNavigation: true,

        selectionRecords: [],

        selectionRecordCollection: null,

        models: [],

        treeAllowed: true,

        selectionRecordModels: [],

        init: function () {
            Dep.prototype.init.call(this);
            if (this.options.params.selectionViewMode && this.availableModes.includes(this.options.params.selectionViewMode)) {
                this.selectionViewMode = this.options.params.selectionViewMode;
                this.selectionRecordModels = this.options.params.models;
            }
        },

        setup: function () {
            if (!this.selectionRecordModels && ['merge', 'compare'].includes(this.selectionViewMode)) {
                this.wait(true)
                this.reloadModels(() => {
                    Dep.prototype.setup.call(this);
                    this.setupCustomButtons();
                    this.wait(false)
                });
            } else {
                Dep.prototype.setup.call(this);
                this.setupCustomButtons();
            }

            this.listenTo(this.model, 'sync', () => {
                this.setupCustomButtons();
                if (this.isRendered()) {
                    this.renderLeftPanel();
                }
            })

            this.listenTo(this.model, 'after:change-mode after:unrelate', (mode) => {
                if (mode === 'detail') {
                    this.setupCustomButtons();
                }
            });

            this.listenTo(this.model, 'init-collection:selectionRecords', (collection) => {
                this.listenTo(collection, 'sync', () => {
                    this.selectionRecords = collection.models.map(m => m.attributes);
                    window.treePanelComponent.rebuildTree();
                    if (collection.models.length > 1) {
                        this.enableButtons()
                    }
                });
            });

            this.listenTo(this.model, 'selection-record:loaded', (list) => {
                this.selectionRecords = list;
                window.treePanelComponent.rebuildTree();
            });

            this.listenToOnce(this, 'after:render', () => {
                let record = this.getMainRecord();
                if (!record || typeof record.isPanelsLoading !== "function") {
                    return;
                }

                if (record.isPanelsLoading()) {
                    this.notify(this.translate('Loading...'));
                    $('#main > .content-wrapper > main').css('overflow-y', 'hidden')
                }
            })

        },

        getSelectionRecordEntityIds() {
            let selectionRecordIds = {};
            if (this.selectionViewMode === 'standard') {
                for (let item of this.selectionRecords) {
                    if (!selectionRecordIds[item.entityType]) {
                        selectionRecordIds[item.entityType] = [];
                    }

                    selectionRecordIds[item.entityType].push(item.entityId);
                }
            } else {
                for (let model of this.selectionRecordModels) {
                    if (!selectionRecordIds[model.name]) {
                        selectionRecordIds[model.name] = [];
                    }

                    selectionRecordIds[model.name].push(model.id);
                }
            }

            return selectionRecordIds;
        },

        getTotalRecords() {
            if (this.selectionViewMode === 'standard') {
                return this.selectionRecords.length;
            } else {
                return this.selectionRecordModels.length;
            }
        },

        setupCustomButtons() {
            if (!this.model.get('entities')) {
                return;
            }

            this.addMenuItem('buttons', {
                name: 'merge',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'merge' ? 'primary' : null,
                html: '<i class="ph ph-arrows-merge "></i> ' + this.translate('Merge'),
                disabled: true,
            }, true, false, true);

            this.addMenuItem('buttons', {
                name: 'compare',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'compare' ? 'primary' : null,
                html: '<i class="ph ph-arrows-left-right"></i> ' + this.translate('Compare'),
                disabled: true
            }, true, false, true);

            this.addMenuItem('buttons', {
                name: 'standard',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'standard' ? 'primary' : null,
                html: '<i class="ph ph-list"></i> ' + this.translate('Standard'),
                disabled: true
            }, true, false, true);
        },

        updateUrl(mode = null) {
            mode = mode ?? this.selectionViewMode;
            const link = '#Selection/view/' + this.model.id + '/selectionViewMode=' + mode;
            this.getRouter().navigate(link, {trigger: false});
        },

        actionShowSelectionView: function (data) {
            if (this.selectionViewMode === data.name) {
                return;
            }

            if (!this.availableModes.includes(data.name)) {
                return;
            }

            this.updateUrl(data.name);

            // if we change from compare to merge or vis-versa
            if (['compare', 'merge'].includes(this.selectionViewMode) && ['compare', 'merge'].includes(data.name)) {
                this.selectionViewMode = data.name;
                let record = this.getMainRecord();
                if (record) {
                    this.reloadStyle(this.selectionViewMode);
                    data.name === 'merge' ? record.applyMerge() : record.cancelMerging();
                    this.setupCustomButtons();
                    window.dispatchEvent(new CustomEvent('record:buttons-update', {
                        detail: Object.assign({
                            headerButtons: this.getMenu()
                        }, data.name === 'merge' ? this.getMergeButtons(false) : this.getCompareButtons())
                    }));
                    return;
                }
            }

            this.selectionViewMode = data.name;
            if (['compare', 'merge'].includes(this.selectionViewMode)) {
                this.notify(this.translate('Loading...'));
                this.reloadModels(() => this.refreshContent());
            } else {
                this.refreshContent();
            }
        },

        reloadModels(callback) {
            List.prototype.loadSelectionRecordModels.call(this, this.model.id).then(models => {
                this.selectionRecordModels = models;
                if (callback) {
                    callback();
                }
            });
        },

        refreshContent() {
            if (this.comparisonAcrossEntities()) {
                this.selectionViewMode = 'standard';
            }
            this.reloadStyle(this.selectionViewMode);

            this.setupRecord();
        },

        reloadStyle(selected = null) {
            selected = selected ?? this.selectionViewMode;
            ['compare', 'standard', 'merge'].forEach(name => {
                $(`.action[data-name="${name}"]`).removeClass('primary');
            })

            $(`.action[data-name="${selected}"]`).addClass('primary');

            if (this.comparisonAcrossEntities()) {
                ['compare', 'merge'].forEach(name => {
                    $(`.action[data-name="${name}"]`).addClass('disabled').attr('disabled', true);
                })
            }
        },

        setupRecord: function () {
            if (['compare', 'merge'].includes(this.selectionViewMode)
                && (
                    this.comparisonAcrossEntities()
                    || this.selectionRecordModels.length < 2
                    || !(this.getEntityTypes().map(e => this.getAcl().check(e, 'read')).reduce((prev, current) => prev && current, true)))
                ) {
                if (this.selectionRecordModels.length < 2) {
                    this.notify('You need at least two item for comparison', 'error');
                }

                this.selectionViewMode = 'standard';
                this.updateUrl()
                this.refreshContent();
                return;
            }
            const o = {
                model: this.model,
                selectionId: this.model.id,
                el: '#main main > .record',
                rootUrl: this.options.params.rootUrl,
                hasNext: this.hasNext,
                models: this.selectionRecordModels
            };

            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);

            this.notify(this.translate('Loading...'));

            this.createView('record', this.getRecordViewName(), o, view => {
                if (this.isRendered()) {
                    view.render();
                }
                this.listenTo(view, 'detailPanelsLoaded', data => {
                    if (!this.panelsList) {
                        this.standardPanelList = data.list;
                    }
                    this.panelsList = data.list;
                    window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                });

                if (this.selectionViewMode === 'standard') {

                    this.panelsList = this.standardPanelList;

                    if (view.isRendered()) {
                        window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                    }

                    this.listenTo(view.model, 'change', () => {
                        window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                    });

                    this.listenTo(view, 'after:render', view => {
                        window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                    });
                }


                this.listenTo(view, 'merge-success', () => {
                    this.selectionViewMode = 'standard';
                    this.updateUrl(this.selectionViewMode);
                    this.refreshContent()
                });


                if (this.isRendered()) {
                    this.setupCustomButtons();
                    window.dispatchEvent(new CustomEvent('record:buttons-update', {
                        detail: Object.assign({
                            headerButtons: this.getMenu()
                        }, view.getRecordButtons())
                    }));
                    this.notify(false);
                }

                this.listenToOnce(view, 'after:render', () => {
                    window.dispatchEvent(new CustomEvent('record:buttons-update', {
                        detail: Object.assign({
                            headerButtons: this.getMenu()
                        }, view.getRecordButtons())
                    }));
                });

                this.listenTo(view, 'all-panels-rendered', () => {
                    this.enableButtons()
                    $('#main > .content-wrapper > main').css('overflow-y', 'auto')
                });
            });
        },

        enableButtons() {
            ['standard', 'compare', 'merge'].forEach(action => {
                if (['compare', 'merge'].includes(action) && this.comparisonAcrossEntities()) {
                    return;
                }

                if (action === 'merge' && !this.getAcl().check(this.model.get('entities')[0], 'create')) {
                    return;
                }

                if (action === 'compare' && this.getEntityTypes().length) {
                    let shouldDisabled = false;
                    for (const entityType of this.getEntityTypes()) {
                        if (!this.getAcl().check(entityType, 'read')) {
                            shouldDisabled = true;
                            break;
                        }
                    }
                    if (shouldDisabled) {
                        return;
                    }
                }

                $(`button[data-name="${action}"]`).removeClass('disabled');
                $(`button[data-name="${action}"]`).attr('disabled', false);
            })
        },

        comparisonAcrossEntities: function () {
            return this.getEntityTypes().length !== 1;
        },

        getRecordViewName: function () {
            if (this.selectionViewMode === 'compare') {
                return 'views/selection/record/detail/compare';
            }

            if (this.selectionViewMode === 'merge') {
                return 'views/selection/record/detail/merge';
            }

            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        actionAddItem() {
            let maxComparableItem = this.getConfig().get('maxComparableItem') || 10;

            if (this.getTotalRecords() >= maxComparableItem) {
                this.notify(this.translate('selectNoMoreThan', 'messages').replace('{count}', maxComparableItem), 'error');
                return;
            }

            this.setupCustomButtons();
            let scope = 'SelectionRecord';
            let viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            let attributes = {_entityFrom: _.extend(this.model.attributes, {_entityName: this.model.name})};

            if (this.getMetadata().get(['scopes', scope, 'hasOwner'])) {
                attributes.ownerUserId = this.getUser().id;
                attributes.ownerUserName = this.getUser().get('name');
            }
            if (this.getMetadata().get(['scopes', scope, 'hasAssignedUser'])) {
                attributes.assignedUserId = this.getUser().id;
                attributes.assignedUserName = this.getUser().get('name');
            }

            this.createView('quickCreate', viewName, {
                scope: scope,
                fullFormDisabled: true,
                relate: {
                    model: this.model,
                    link: 'selections',
                    panelName: 'selectionRecords'
                },
                layoutRelatedScope: "Selection.selectionRecords",
                attributes: attributes,
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    if (this.mode !== 'edit') {
                        this.model.trigger('after:relate', 'selections');
                        this.model.fetch().then(() => {
                            if (['compare', 'merge'].includes(this.selectionViewMode)) {
                                this.notify(this.translate('Loading...'));
                                this.reloadModels(() => this.refreshContent())
                            } else {
                                this.refreshContent();
                            }
                        });
                    }
                });
            });
        },

        afterRender() {
            this.treeAllowed = false
            Dep.prototype.afterRender.call(this);
            this.renderLeftPanel();
        },

        renderLeftPanel() {
            if (window.treePanelComponent) {
                try {
                    window.treePanelComponent.$destroy();
                } catch (e) {
                }
            }
            let entities = this.model.get('entities') || [];
            this.selectedScope = this.selectedScope ?? (entities.length > 0 ? this.model.get('entities')[0] : null)
            let view = this.getMainRecord();
            let entitySelectionModel = new Model();

            window.treePanelComponent = new Svelte.TreePanel({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                props: {
                    scope: this.scope,
                    model: this.model,
                    mode: 'detail',
                    showApplyQuery: false,
                    showApplySortOrder: false,
                    canBuildTree: entities.length > 0,
                    selectedScope: this.selectedScope,
                    canOpenNode: false,
                    showEntitySelector: true,
                    callbacks: {
                        selectNode: data => {
                            if (!this.getAcl().check('Selection', 'edit')) {
                                return
                            }
                            if (window.treePanelComponent.getActiveItem() !== '_self') {
                                view.selectNode(data);
                                return;
                            }

                            let selected = false;
                            if (entitySelectionModel.get('entityId') && this.getSelectionRecordEntityIds()[entitySelectionModel.get('entityId')]) {
                                selected = this.getSelectionRecordEntityIds()[entitySelectionModel.get('entityId')].includes(data.id);
                            }

                            if (selected) {
                                this.deleteSelectionRecords(entitySelectionModel.get('entityId'), data.id);
                            } else {
                                let maxComparableItem = this.getConfig().get('maxComparableItem') || 10;

                                if (this.getTotalRecords() >= maxComparableItem) {
                                    this.notify(this.translate('selectNoMoreThan', 'messages').replace('{count}', maxComparableItem), 'error');
                                    return;
                                }

                                if (entitySelectionModel.get('entityId')
                                    && this.getSelectionRecordEntityIds()[entitySelectionModel.get('entityId')]
                                    && this.getSelectionRecordEntityIds()[entitySelectionModel.get('entityId')].includes(data.id)) {
                                    return;
                                }

                                this.notify(this.translate('Adding...'));
                                this.ajaxPostRequest(`SelectionRecord`, {
                                    entityType: entitySelectionModel.get('entityId'),
                                    entityId: data.id,
                                    selectionsIds: [this.model.id]
                                }).then(_ => {
                                    this.model.fetch().then(_ => {
                                        if (['compare', 'merge'].includes(this.selectionViewMode)) {
                                            this.notify(this.translate('Loading...'));
                                            this.reloadModels(() => {
                                                this.refreshContent();
                                                window.treePanelComponent.rebuildTree();
                                            });
                                            this.notify(this.notify(this.translate('Done'), 'success'));
                                        } else {
                                            this.refreshContent();
                                            this.notify(this.notify(this.translate('Done'), 'success'));
                                        }
                                    });
                                })
                            }
                        },

                        treeLoad: (treeScope, treeData) => {
                            if (view && view.treeLoad && typeof view.treeLoad === 'function') {
                                view.treeLoad(data);
                            }
                        },

                        treeReset: () => {
                            if (view && typeof view.treeReset === 'function') {
                                view.treeReset(data);
                            }
                        },

                        treeWidthChanged: (width) => {
                            if (view && typeof view.onTreeResize === 'function') {
                                view.onTreeResize(width)
                            }
                        },

                        shouldBeSelected: (activeItem, nodeId) => {
                            if (activeItem !== '_self') {
                                return;
                            }

                            if (entitySelectionModel.get('entityId') && this.getSelectionRecordEntityIds()[entitySelectionModel.get('entityId')]) {
                                return this.getSelectionRecordEntityIds()[entitySelectionModel.get('entityId')].includes(nodeId);
                            }
                            return false;
                        },

                        onEntitySelectorAvailable: (element) => {
                            if (entities.length) {
                                entitySelectionModel.set('entityId', this.selectedScope);
                                entitySelectionModel.set('entityName', this.translate(this.selectedScope, 'scopeNames'));
                            }

                            this.createView('entitySelect', 'views/selection-record/fields/entity-type', {
                                el: `${this.options.el} .content-wrapper .entity-selector`,
                                model: entitySelectionModel,
                                name: 'entityId',
                                params: {
                                    required: true
                                },
                                mode: 'edit',
                                createDisabled: true
                            }, (view) => {
                                view.render();
                                this.listenTo(view, 'change', () => {
                                    window.treePanelComponent.setSelectedScope(entitySelectionModel.get('entityId'));
                                    this.selectedScope = entitySelectionModel.get('entityId');
                                    window.treePanelComponent.setCanBuildTree(true);
                                    window.treePanelComponent.rebuildTree()
                                })
                            })
                        }
                    },
                }
            })
        },

        deleteSelectionRecords(entityType, id) {
            let recordsToDelete = [];
            if (this.selectionViewMode === 'standard') {
                recordsToDelete = this.selectionRecords.filter(record => record.entityId === id && record.entityType === entityType).map(r => r.id);
            } else {
                recordsToDelete = this.selectionRecordModels.filter(m => m.id === id && m.name === entityType).map(m => m.get('_selectionRecordId'));
            }

            let promises = [];
            for (const recordId of recordsToDelete) {
                promises.push(new Promise((resolve, reject) => {
                    $.ajax({
                        url: `SelectionRecord/${recordId}`,
                        type: 'DELETE',
                        contentType: 'application/json',
                        success: () => {
                            resolve();
                        },
                        error: () => {
                            reject()
                        },
                    });
                }))
            }
            this.notify(this.translate('Removing...'));
            Promise.all(promises).then(() => {
                this.model.fetch().then(_ => {
                    this.afterRemoveSelectedRecords(recordsToDelete)
                });
            });
        },

        afterRemoveSelectedRecords(selectedRecordIds) {
            if (this.selectionViewMode !== 'standard') {
                this.selectionRecordModels = this.selectionRecordModels.filter(m => !selectedRecordIds.includes(m.get('_selectionRecordId')))
                window.treePanelComponent.rebuildTree();
            } else {
                this.selectionRecords = this.selectionRecords.filter(record => !selectedRecordIds.includes(record.id))
            }

            this.refreshContent();
            this.notify(this.notify(this.translate('Done'), 'success'));
        },

        afterChangedSelectedRecords(_) {
            this.notify(this.translate('Loading...'));
            this.reloadModels(() => {
                this.refreshContent();
                window.treePanelComponent.rebuildTree();
            });
            this.notify(this.notify(this.translate('Done'), 'success'));
        },

        getCompareButtons() {
            let buttons = {
                additionalButtons: [],
                buttons: [],
                dropdownButtons: [
                    {
                        label: this.translate('Remove'),
                        name: 'delete'
                    },
                    {
                        label: this.translate('Duplicate'),
                        name: 'duplicate'
                    }
                ]
            }

            if (this.getAcl().check('Selection', 'edit')) {
                buttons.additionalButtons.push({
                    action: 'addItem',
                    name: 'addItem',
                    label: this.translate('addItem')
                })
            }

            return buttons;
        },

        getMergeButtons(disabled = true) {
            return Object.assign(this.getCompareButtons(), this.getAcl().check(this.model.get('entities')[0], 'create') ? {
                buttons: [{
                    label: this.translate('Merge'),
                    name: 'merge',
                    style: 'primary',
                    disabled: disabled
                }]
            } : {});
        },

        getEntityTypes() {
            if (this.model.get('entities')) {
                return this.model.get('entities');
            }

            if (this.selectionRecordModels) {
                let entityTypes = [];
                this.selectionRecordModels.forEach(m => {
                    if (!entityTypes.includes(m.name)) {
                        entityTypes.push(m.name);
                    }
                });
                return entityTypes;
            }

            return  [];
        },

        actionMerge() {
            if (this.selectionViewMode !== 'merge') {
                return;
            }

            this.getMainRecord()?.applyMerge();
        }
    });
});

