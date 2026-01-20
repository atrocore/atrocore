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

        selectionViewMode: 'compare',

        availableModes: ['standard', 'compare', 'merge'],

        hidePanelNavigation: true,

        selectionRecords: [],

        selectionRecordCollection: null,

        models: [],

        treeAllowed: true,

        selectionRecordModels: [],

        selectedIds: [],

        maxForComparison: 5,

        collection: null,

        layoutData: {},

        scope: 'Selection',

        link: 'selectionRecords',

        inverseLink: 'selection',

        itemScope: 'SelectionRecord',

        entityField: 'entity',

        init: function () {
            Dep.prototype.init.call(this);
            if (this.options.params.selectionViewMode && this.availableModes.includes(this.options.params.selectionViewMode)) {
                this.selectionViewMode = this.options.params.selectionViewMode;
                this.selectionRecordModels = this.options.params.models;
                if (this.selectionRecordModels) {
                    this.selectedIds = [];
                    for (const model of this.selectionRecordModels) {
                        if (this.selectedIds.length >= this.maxForComparison) {
                            break;
                        }

                        this.selectedIds.push(model.id);
                    }
                }
            }
        },

        setup: function () {
            if (!this.selectionRecordModels?.length && ['merge', 'compare'].includes(this.selectionViewMode)) {
                this.wait(true)
                this.reloadModels(() => {
                    if (this.selectionRecordModels.length === 0) {
                        this.selectionViewMode = 'standard';
                    }
                    Dep.prototype.setup.call(this);
                    this.setupCustomButtons();
                });
            } else {
                Dep.prototype.setup.call(this);
                this.setupCustomButtons();
            }

            this.listenTo(this.model, 'sync', () => {
                if (this.isRendered()) {
                    this.renderLeftPanel();
                }
            });

            this.listenTo(this.model, 'sync after:inlineEditSave after:set-detail-mode', () => {
                this.setupCustomButtons();
                setTimeout(() => this.enableButtons(), 300);
            });

            this.listenTo(this.model, 'after:unrelate', () => {
                this.refreshContent();

                this.notify(this.notify(this.translate('Done'), 'success'));
            });

            this.listenTo(this.model, 'after:relate', () => {
                this.setupCustomButtons();
                this.notify(this.translate('Loading...'));
                this.model.fetch().then(() => {
                    if (['compare', 'merge'].includes(this.selectionViewMode)) {
                        this.reloadModels(() => this.refreshContent());
                        this.notify(this.notify(this.translate('Done'), 'success'));
                    } else {
                        this.refreshContent();
                        this.notify(this.notify(this.translate('Done'), 'success'));
                    }
                });
            });

            this.listenTo(this.model, 'init-collection:'+this.link, (collection) => {
                this.collection = collection;
                this.listenTo(collection, 'sync', () => {
                    if (this.selectionViewMode === 'standard' && window.leftSidePanel) {
                        window.leftSidePanel?.setRecords(this.getRecordForPanels());
                    }

                    if (collection.models.length > 1) {
                        this.enableButtons()
                    }
                });
            });

            this.listenToOnce(this, 'after:render', () => {
                let record = this.getMainRecord();
                if (!record || typeof record.isPanelsLoading !== "function") {
                    return;
                }

                if (record.isPanelsLoading()) {
                    $('#main > .content-wrapper > main').css('overflow-y', 'hidden')
                } else {
                    setTimeout(() => this.enableButtons(), 300)
                }
            });

            this.notify(this.translate('Loading...'));
        },

        isActiveMerge() {
            return this.availableModes.includes('merge') && (!this.model.get('type') || this.model.get('type') === 'single');
        },

        setupCustomButtons() {
            if (this.getEntityTypes().length === 0) {
                return;
            }

            if(this.availableModes.includes('merge')) {
                this.addMenuItem('buttons', {name: 'merge', style: 'hidden'}, true, false, true);
            }

            if (this.isActiveMerge()) {
                this.addMenuItem('buttons', {
                    name: 'merge',
                    action: 'showSelectionView',
                    style: this.selectionViewMode === 'merge' ? 'primary' : null,
                    html: '<i class="ph ph-arrows-merge"></i>',
                    tooltip: this.translate('Merge'),
                    disabled: true,
                    className: 'selection-view-switcher'
                }, true, false, true);
            }

            if(this.availableModes.includes('compare')) {
                this.addMenuItem('buttons', {
                    name: 'compare',
                    action: 'showSelectionView',
                    style: this.selectionViewMode === 'compare' ? 'primary' : null,
                    html: '<i class="ph ph-arrows-left-right"></i>',
                    tooltip: this.translate('Compare'),
                    disabled: true,
                    className: 'selection-view-switcher'
                }, true, false, true);
            }

            if(this.availableModes.includes('standard')) {
                this.addMenuItem('buttons', {
                    name: 'standard',
                    action: 'showSelectionView',
                    style: this.selectionViewMode === 'standard' ? 'primary' : null,
                    html: '<i class="ph ph-list"></i>',
                    tooltip: this.translate('Standard'),
                    disabled: true,
                    className: 'selection-view-switcher'
                }, true, false, true);
            }
        },

        updateUrl(mode = null) {
            mode = mode ?? this.selectionViewMode;
            const link = '#' + this.scope + '/view/' + this.model.id + '/selectionViewMode=' + mode;
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

            if (window.leftSidePanel) {
                window.leftSidePanel?.setSelectionViewMode(data.name);
            }

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
                    record.reRender();
                    return;
                }
            }

            this.selectionViewMode = data.name;

            if (window.treePanelComponent) {
                window.treePanelComponent.setShowItems(['compare', 'merge'].includes(data.name));
            }

            if (['compare', 'merge'].includes(this.selectionViewMode)) {
                this.notify(this.translate('Loading...'));
                this.reloadModels(() => this.refreshContent());
            } else {
                this.refreshContent();
            }
        },

        reloadModels(callback) {
            this.loadSelectionRecordModels(this.model.id).then(models => {
                this.selectionRecordModels = models;
                //we clean to remove dead id
                this.selectedIds = this.selectedIds.filter(id => models.map(m => m.id).includes(id));
                if (this.selectedIds.length === 0) {
                    for (const model of this.selectionRecordModels) {
                        if (this.selectedIds.length >= this.maxForComparison) {
                            break;
                        }

                        this.selectedIds.push(model.id);
                    }
                }

                if (window.leftSidePanel) {
                    window.leftSidePanel?.setRecords(this.getRecordForPanels());
                    window.leftSidePanel?.setSelectedIds(this.selectedIds);
                }

                if (callback) {
                    callback();
                }
            });
        },

        getItemsUrl(selectionId) {
            return `selection/${selectionId}/selectionRecords?select=name,entityType,entityId,entity&collectionOnly=true&sortBy=createdAt&asc=false&offset=0&maxSize=20`;
        },

        loadSelectionRecordModels(selectionId) {
            let models = [];
            return new Promise((initialResolve, reject) => {
                this.ajaxGetRequest(this.getItemsUrl(selectionId))
                    .then(result => {
                        let entityByScope = {};
                        let order = 0;
                        for (const entityData of result.list) {
                            let scope = entityData.entityType ?? entityData.entityName;
                            if (!entityByScope[scope]) {
                                entityByScope[scope] = [];
                            }
                            entityData.entity._order = order;
                            entityData.entity._selectionRecordId = entityData.id;

                            entityByScope[scope].push(entityData.entity);
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
                                        models.push(currentModel);
                                    }
                                    resolve();
                                })
                            }));
                        }

                        Promise.all(promises)
                            .then(() => {
                                models.sort((a, b) => a._order - b._order);
                                let orderedModels = [];
                                if(this.hasStaging()) {
                                    // we order by entity, master first then staging
                                    for (const entityType of this.getEntityTypes()) {
                                        models.forEach(m => {
                                            if(m.name === entityType) {
                                                orderedModels.push(m);
                                            }
                                        })
                                    }
                                    initialResolve(orderedModels);
                                    return;
                                }
                                initialResolve(models);
                            });
                    });
            });
        },

        getRecordForPanels() {
            if (!this.selectionRecordModels) {
                return [];
            }

            return this.selectionRecordModels.map(model => {
                return {
                    id: model.id,
                    name: this.getModelTitle(model),
                    entityType: model.name
                }
            });
        },

        refreshContent() {
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
                $(`.action[data-name="merge"]`).addClass('disabled').attr('disabled', true);
            }

            const header = $('.page-header');
            if (this.selectionViewMode === 'standard') {
                header.removeClass('selection-header');
            } else {
                header.addClass('selection-header');
            }
        },

        scrollToPanel(name) {
            let panel = $('#main').find(`.panel[data-name="${name}"], tbody[data-name="${name}"]`);
            if (panel.size() > 0) {
                const header = document.querySelector('.page-header');
                const content = document.querySelector("main") || document.querySelector('#main');
                panel = panel.get(0);

                if (!content || !panel) return;

                const panelOffset = panel.getBoundingClientRect().top + content.scrollTop - content.getBoundingClientRect().top;
                const stickyOffset = this.selectionViewMode === 'standard' ? header.offsetHeight : 50;
                content.scrollTo({
                    top: window.screen.width < 768 ? panelOffset : panelOffset - stickyOffset,
                    behavior: "smooth"
                });
            }
        },

        getVisiblePanels() {
            return (this.panelsList ?? []).filter(panel => {
                if (panel.name === 'panel-0') {
                    return true;
                }

                if (this.isPanelClosed(panel)) {
                    return true;
                }

                const panelElement = document.querySelector(`.panel[data-name="${panel.name}"], tbody[data-name="${panel.name}"]`);

                return panelElement && panelElement.style.display !== 'none' && !$(panelElement).hasClass('hidden');
            });
        },

        setupRecord: function () {
            if (this.selectionViewMode === 'merge'
                && (
                    this.comparisonAcrossEntities()
                    || this.selectionRecordModels.length < 2
                    || !(this.getEntityTypes().map(e => this.getAcl().check(e, 'read')).reduce((prev, current) => prev && current, true)))
            ) {
                if (this.selectionRecordModels.length < 2) {
                    this.notify(this.translate('youNeedAtLeastTwoItem', 'messages', 'Selection'), 'error');
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
                entityTypes: this.getEntityTypes(),
                scope: this.getEntityTypes()[0]
            };

            if (this.selectionRecordModels) {
                o.models = this.selectionRecordModels.filter(m => this.selectedIds.includes(m.id));
            }

            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);

            this.notify(this.translate('Loading...'));

            let createView = () => this.createView('record', this.getRecordViewName(), o, view => {
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

                this.listenToOnce(view, 'after:render', () => {
                    if (this.selectionViewMode === 'standard') {
                        this.notify(false);
                    }
                    this.setupCustomButtons();
                    window.dispatchEvent(new CustomEvent('record:buttons-update', {
                        detail: Object.assign({
                            headerButtons: this.getMenu()
                        }, view.getRecordButtons())
                    }));
                });

                this.listenTo(view, 'all-panels-rendered', () => {
                    $('#main > .content-wrapper > main').css('overflow-y', 'auto');
                    this.enableButtons();
                    this.notify(false);
                });

                this.listenTo(view, 'layout-refreshed', () => {
                    this.setupRecord();
                })

                if (this.isRendered()) {
                    view.render();
                }
            });

            if (this.comparisonAcrossEntities() && this.selectionViewMode !== 'standard') {
                this.loadLayoutData(() => {
                    o.layoutData = this.layoutData;
                    createView();
                    this.wait(false);
                });
            } else {
                createView();
                this.wait(false);
            }
        },

        enableButtons() {
            this.availableModes.forEach(action => {

                if (action === 'merge' && this.comparisonAcrossEntities()) {
                    return;
                }

                if (action === 'merge' && this.getEntityTypes().length && !this.getAcl().check(this.getEntityTypes()[0], 'create')) {
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
            });
        },

        comparisonAcrossEntities: function () {
            return this.getEntityTypes().length > 1;
        },

        getRecordViewName: function () {
            if (this.selectionViewMode === 'compare') {
                if (this.comparisonAcrossEntities()) {
                    return 'views/selection/record/detail/compare-entities';
                }
                return 'views/selection/record/detail/compare';
            }

            if (this.selectionViewMode === 'merge') {
                return 'views/selection/record/detail/merge';
            }

            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        shouldOpenSelectDialog() {
            return this.model.get('type') === 'single';
        },

        actionAddItem() {
            if(this.model.get('type') === 'single' && !this.model.get('entity')) {
                this.notify(this.translate('entityIsRequired', 'messages', 'Selection'), 'error');
                return;
            }
            debugger

            if (this.shouldOpenSelectDialog()) {
                let foreignScope = this.getEntityTypes()[0];
                let viewName = this.getMetadata().get('clientDefs.' + foreignScope + '.modalViews.select') || 'views/modals/select-records';
                this.notify('Loading...');
                this.createView('selectRecords', viewName, {
                    scope: foreignScope,
                    createButton: false,
                }, view => {
                    view.render();
                    this.notify(false);
                    this.listenToOnce(view, 'select', function (model) {
                        this.clearView('selectRecords');
                        this.ajaxPostRequest('SelectionRecord', {
                            entityType: foreignScope,
                            entityId: model.id,
                            selectionId: this.model.id
                        }).then(() => {
                            this.model.trigger('after:relate', this.link);
                            if (this.toggleSelected(model.id)) {
                                window.leftSidePanel?.setSelectedIds(this.selectedIds);
                            }
                        })
                    }, this);
                });
            } else {
                this.setupCustomButtons();
                let scope = this.itemScope;
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
                        link: this.inverseLink,
                        panelName: this.link,
                    },
                    layoutRelatedScope: this.scope + '.' + this.link,
                    attributes: attributes,
                }, view => {
                    view.render();
                    view.notify(false);
                    this.listenToOnce(view, 'after:save', () => {
                        let model = view.getView('record')?.model;
                        if (model) {
                            if (this.toggleSelected(model.get('entityId'))) {
                                window.leftSidePanel?.setSelectedIds(this.selectedIds);
                            }
                        }
                        this.model.trigger('after:relate', this.link);
                    });
                });
            }
        },


        afterRender() {
            this.treeAllowed = false
            Dep.prototype.afterRender.call(this);
            this.renderLeftPanel();

            const header = $('.page-header');
            if (this.selectionViewMode === 'standard') {
                header.removeClass('selection-header');
            } else {
                header.addClass('selection-header');
            }
        },

        setupLayoutEditorButton() {
            if (this.selectionViewMode !== 'standard' && !this.comparisonAcrossEntities() && this.getMainRecord()) {
                this.getMainRecord().createLayoutConfigurator();
            }
        },

        initSelectLeftPanel() {
            if (['compare', 'merge'].includes(this.selectionViewMode) && !this.getStorage().get('treeItem', this.scope)) {
                this.getStorage().set('treeItem', this.scope, '_items');
            } else if (this.selectionViewMode === 'standard' && this.getStorage().get('treeItem', this.scope) === '_items') {
                this.getStorage().clear('treeItem', this.scope);
            }
        },

        renderLeftPanel() {
            this.initSelectLeftPanel();
            if (window.treePanelComponent) {
                try {
                    window.treePanelComponent.$destroy();
                } catch (e) {
                }
            }
            window.treePanelComponent = new Svelte.TreePanel({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                props: {
                    scope: this.scope,
                    model: this.model,
                    mode: 'detail',
                    showItems: ['compare', 'merge'].includes(this.selectionViewMode),
                    hasItems: true,
                    callbacks: {
                        selectNode: data => {
                            window.location.href = `/#${this.scope}/view/${data.id}`;
                        },
                        afterMounted: () => {
                            if (this.selectionViewMode === 'standard') {
                                $('a[data-name="_items"]').addClass('hidden');
                            }
                        },
                        onActiveItems: (element) => {
                            if (window.leftSidePanel) {
                                try {
                                    window.leftSidePanel.$destroy();
                                } catch (e) {
                                }
                            }

                            window.leftSidePanel = new Svelte.SelectionLeftSidePanel({
                                target: element,
                                props: {
                                    records: this.getRecordForPanels(),
                                    selectedIds: this.selectedIds,
                                    selectionViewMode: this.selectionViewMode,
                                    onItemClicked: (e, itemId) => {
                                        if (this.selectionViewMode === 'standard') {
                                            return;
                                        }
                                        e.preventDefault();

                                        if (this.toggleSelected(itemId)) {
                                            window.leftSidePanel?.setSelectedIds(this.selectedIds);
                                            this.setupRecord();
                                        }
                                    },
                                    onSelectAll: (entityType) => {
                                        let shouldReload = false;
                                        this.selectionRecordModels.forEach(model => {
                                            if (model.name === entityType && !this.selectedIds.includes(model.id)) {
                                                if (this.toggleSelected(model.id)) {
                                                    shouldReload = true;
                                                }
                                            }
                                        });

                                        if (shouldReload) {
                                            window.leftSidePanel?.setSelectedIds(this.selectedIds);
                                            this.setupRecord();
                                        }
                                    },
                                    onUnSelectAll: (entityType) => {
                                        let shouldReload = false;
                                        this.selectionRecordModels.reverse().forEach(model => {
                                            if (model.name === entityType && this.selectedIds.includes(model.id)) {
                                                if (this.toggleSelected(model.id)) {
                                                    shouldReload = true;
                                                }
                                            }
                                        });

                                        if (shouldReload) {
                                            window.leftSidePanel?.setSelectedIds(this.selectedIds);
                                            this.setupRecord();
                                        }
                                    }
                                }
                            })
                        }
                    }
                }
            });
        },

        afterRemoveSelectedRecords(selectedRecordIds) {
            this.selectionRecordModels = this.selectionRecordModels.filter(m => !selectedRecordIds.includes(m.get('_selectionRecordId')))

            if (this.selectionRecordModels.length === 0) {
                this.actionShowSelectionView({name: 'standard'});
                return;
            }

            window.leftSidePanel?.setRecords(this.getRecordForPanels());

            if (this.selectionRecordModels.length === 2) {
                this.selectedIds = this.selectionRecordModels.map(m => m.id);
            } else {
                this.selectedIds = this.selectedIds.filter(id => this.selectionRecordModels.find(v => v.id === id))
            }

            window.leftSidePanel?.setSelectedIds(this.selectedIds);

            this.model.trigger('after:unrelate')
        },

        toggleSelected(itemId) {
            if (this.selectedIds.includes(itemId)) {
                if (this.selectedIds.length === 2) {
                    this.notify(this.translate('minimumRecordForComparison', 'messages').replace('{count}', 2));
                    return;
                }
                this.selectedIds = this.selectedIds.filter(id => id !== itemId);
            } else {
                let maxComparableItem = this.getConfig().get('maxComparableItem') || 10;

                if (this.selectedIds.length >= maxComparableItem) {
                    this.notify(this.translate('selectNoMoreThan', 'messages').replace('{count}', maxComparableItem));
                    return;
                }

                this.selectedIds.push(itemId);
            }
            return true;
        },

        afterChangedSelectedRecords(changedIds) {
            this.notify(this.translate('Loading...'));
            this.selectedIds = this.selectedIds.concat(changedIds);
            this.reloadModels(() => {
                this.refreshContent();
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
                ],
                hasLayoutEditor: true
            }

            if (this.getAcl().check(this.scope, 'edit')) {
                buttons.additionalButtons.push({
                    action: 'addItem',
                    name: 'addItem',
                    label: this.translate('addItem')
                })
            }

            return buttons;
        },

        getMergeButtons(disabled = true) {
            return Object.assign(this.getCompareButtons(), this.getEntityTypes().length && this.getAcl().check(this.getEntityTypes()[0], 'create') ? {
                buttons: [{
                    label: this.translate('Merge'),
                    name: 'merge',
                    style: 'primary',
                    disabled: disabled
                }]
            } : {});
        },

        hasLayoutEditor() {
            return this.selectionViewMode !== 'standard' && this.getAcl().check('LayoutProfile', 'read');
        },

        getEntityTypes() {
            if(this.model.get('type') === 'single') {
                let entities = [this.model.get(this.entityField)];
                let stagingEntity = this.getStagingEntity(this.model.get(this.entityField));
                if (stagingEntity) {
                    entities.push(stagingEntity);
                }
                return entities;
            }

            if (this.selectionRecordModels && this.selectionRecordModels.length) {
                let entityTypes = [];
                this.selectionRecordModels.forEach(m => {
                    if (!entityTypes.includes(m.name)) {
                        entityTypes.push(m.name);
                    }
                });
                return entityTypes;
            }

            return [];
        },

        hasStaging() {
            return !!this.getStagingEntity(this.model.get(this.entityField));
        },

        getStagingEntity(masterEntity) {
            let result = null;
            _.each(this.getMetadata().get(['scopes']), (scopeDefs, scope) => {
                if(scopeDefs.primaryEntityId === masterEntity) {
                    result =  scope;
                }
            })
            return result;
        },

        actionMerge() {
            if (this.selectionViewMode !== 'merge') {
                return;
            }

            this.getMainRecord()?.applyMerge((result) => {
                this.getRouter().navigate(`#${this.getEntityTypes()[0]}/view/${result.id}`, {trigger: true});
            });
        },

        canLoadActivities: function () {
            return true;
        },

        shouldSetupRightSideView: function () {
            return true;
        },

        loadLayoutData(callback) {
            let count = 0;
            this.layoutData = [];
            for (const entityType of this.getEntityTypes()) {
                this.getHelper().layoutManager.get(entityType, 'selection', null, null, data => {
                    let layout = [
                        {
                            label: "",
                            style: "",
                            rows: []
                        }
                    ];

                    for (const fieldData of data.layout) {
                        layout[0].rows.push([{
                            ...fieldData,
                            fullWidth: true
                        }]);

                        if (fieldData.attributeId) {
                            this.selectionRecordModels.forEach(model => {
                                if (model.name !== entityType) {
                                    return;
                                }
                                model.defs.fields[fieldData.name] = fieldData.attributeDefs;
                                model.defs.fields[fieldData.name].disableAttributeRemove = true;
                            });
                        }
                    }

                    this.layoutData[entityType] = {detailLayout: layout, layoutData: data.layout};

                    count++;
                    if (count === this.getEntityTypes().length) {
                        callback();
                    }
                });
            }
        }
    });
});

