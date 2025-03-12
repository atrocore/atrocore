/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/list', ['views/main', 'search-manager', 'lib!JsTree'], function (Dep, SearchManager) {

    return Dep.extend({

        template: 'list',

        scope: null,

        name: 'List',

        headerView: 'views/header',

        searchView: 'views/record/search',

        recordView: 'views/record/list',

        recordKanbanView: 'views/record/kanban',

        searchPanel: true,

        searchManager: null,

        createButton: true,

        quickCreate: false,

        optionsToPass: [],

        storeViewAfterCreate: false,

        storeViewAfterUpdate: true,

        keepCurrentRootUrl: false,

        viewMode: null,

        viewModeList: null,

        defaultViewMode: 'list',

        previousWidth: null,

        init: function () {
            Dep.prototype.init.call(this);

            if (this.options.params.viewMode) {
                this.viewMode = this.options.params.viewMode;
            }
        },

        data: function () {
            return {
                isTreeAllowed: this.isTreeAllowed()
            }
        },

        setup: function () {
            this.collection.maxSize = this.getMetadata().get(`clientDefs.${this.scope}.limit`) || this.getConfig().get('recordsPerPage') || this.collection.maxSize;

            this.collectionUrl = this.collection.url;
            this.collectionMaxSize = this.collection.maxSize;

            this.setupModes();

            this.setViewMode(this.viewMode);

            if (this.getMetadata().get('clientDefs.' + this.scope + '.searchPanelDisabled')) {
                this.searchPanel = false;
            }

            if (this.getMetadata().get(['clientDefs', this.scope, 'createDisabled']) || this.getMetadata().get(['scopes', this.scope, 'disabled'])) {
                this.createButton = false;
            }

            this.entityType = this.collection.name;

            this.headerView = this.options.headerView || this.headerView;
            this.recordView = this.options.recordView || this.recordView;
            this.searchView = this.options.searchView || this.searchView;

            if (this.searchPanel) {
                this.setupSearchManager();
            }

            this.defaultSortBy = this.collection.sortBy;
            this.defaultAsc = this.collection.asc;

            this.setupSorting();

            if (this.createButton) {
                this.setupCreateButton();
            }

            this.getStorage().set('list-view', this.scope, this.viewMode);

        },

        actionDynamicEntityAction(data) {
            let listView = this.getView('list')

            if (listView) {
                listView.massActionDynamicMassAction(data)
            }
        },

        setupModes: function () {
            this.defaultViewMode = this.options.defaultViewMode ||
                this.getMetadata().get(['clientDefs', this.scope, 'listDefaultViewMode']) ||
                this.defaultViewMode;

            this.viewMode = this.viewMode || this.defaultViewMode;

            var viewModeList = this.options.viewModeList ||
                this.viewModeList ||
                this.getMetadata().get(['clientDefs', this.scope, 'listViewModeList']);

            if (viewModeList) {
                this.viewModeList = viewModeList;
            } else {
                this.viewModeList = ['list'];
            }

            if (this.options.params.viewMode) {
                // do not search in cache if view mode is in params
                return
            }

            if (this.viewModeList.length > 1 || this.getMetadata().get(['clientDefs', this.scope, 'kanbanViewMode'])) {
                var modeKey = 'listViewMode' + this.scope;
                if (this.getStorage().has('state', modeKey)) {
                    var storedViewMode = this.getStorage().get('state', modeKey);
                    if (storedViewMode) {
                        if (~this.viewModeList.indexOf(storedViewMode)) {
                            this.viewMode = storedViewMode;
                        }
                    }
                }
                if (!this.viewMode) {
                    this.viewMode = this.defaultViewMode;
                }
            }
        },

        getBreadcrumbsItems() {
            return [
                {
                    url: '#' + this.scope,
                    label: this.getLanguage().translate(this.scope, 'scopeNamesPlural'),
                    className: 'header-title'
                }
            ];
        },

        setupHeader: function () {
            new Svelte.ListHeader({
                target: document.querySelector('#main .page-header'),
                props: {
                    params: {
                        breadcrumbs: this.getBreadcrumbsItems(),
                        scope: this.scope
                    },
                    entityActions: {
                        buttons: this.getMenu().buttons ?? [],
                        dropdownButtons: this.getMenu().dropdownButtons ?? [],
                    },
                    callbacks: {
                        onAddFavorite: (scope) => {
                            this.notify('Saving');
                            const favorites = this.getPreferences().get('favoritesList') || [];
                            const result =  [...favorites, scope];

                            this.getPreferences().save({
                                favoritesList: result,
                            }, {patch: true}).then(() => {
                                this.notify('Saved', 'success');
                                window.dispatchEvent(new CustomEvent('favorites:update', {detail: result}));
                                this.getPreferences().trigger('favorites:update');
                            });
                        },
                        onRemoveFavorite: (scope) => {
                            this.notify('Saving');
                            const favorites = this.getPreferences().get('favoritesList') || [];

                            if (!Array.isArray(favorites) || favorites.length === 0) {
                                throw new Error('Current entity is not in favorites list');
                            }

                            const result = favorites.filter(item => item !== scope);
                            this.getPreferences().save({
                                favoritesList: result
                            }, {patch: true}).then(() => {
                                this.notify('Saved', 'success');
                                window.dispatchEvent(new CustomEvent('favorites:update', {detail: result}));
                                this.getPreferences().trigger('favorites:update');
                            });
                        },
                        canRunAction: (scope, action) => this.getAcl().check(scope, action)
                    },
                    viewMode: this.viewMode,
                    isFavoriteEntity: !!this.getPreferences().get('favoritesList')?.includes(this.scope),
                    onViewModeChange: (mode) => {
                        this.switchViewMode(mode);
                    },
                    renderSearch: () => {
                        if (this.searchPanel) {
                            this.setupSearchPanel();
                        }
                    }
                }
            });
        },

        setupCreateButton: function () {
            if (this.quickCreate) {
                this.menu.buttons.unshift({
                    action: 'quickCreate',
                    label: 'Create ' + this.scope,
                    style: 'primary',
                    acl: 'create',
                    aclScope: this.entityType || this.scope,
                    cssStyle: "margin-left: 15px",
                });
            } else {
                this.menu.buttons.unshift({
                    link: '#' + this.scope + '/create',
                    action: 'create',
                    label: 'Create ' + this.scope,
                    style: 'primary',
                    acl: 'create',
                    aclScope: this.entityType || this.scope,
                    cssStyle: "margin-left: 15px"
                });
            }
        },

        setupSearchPanel: function () {
            let hiddenBoolFilterList = this.getMetadata().get(`clientDefs.${this.scope}.hiddenBoolFilterList`) || [];
            let searchView = this.getMetadata().get(`clientDefs.${this.scope}.recordViews.search`) || this.searchView;

            this.createView('search', searchView, {
                collection: this.collection,
                el: '#main .page-header .search-container',
                searchManager: this.searchManager,
                scope: this.scope,
                viewMode: this.viewMode,
                viewModeList: this.viewModeList,
                hiddenBoolFilterList: hiddenBoolFilterList,
            }, function (view) {
                view.render();
                this.listenTo(view, 'reset', function () {
                    this.resetSorting();
                }, this);

                if (this.viewModeList.length > 1) {
                    this.listenTo(view, 'change-view-mode', this.switchViewMode, this);
                }
            }.bind(this));
        },

        switchViewMode: function (mode) {
            this.clearView('list');
            this.collection.isFetched = false;
            this.collection.reset();
            this.applyStoredSorting();
            this.setViewMode(mode, true);
            this.loadList();
        },

        setViewMode: function (mode, toStore) {
            this.viewMode = mode;

            this.collection.url = this.collectionUrl;
            this.collection.maxSize = this.collectionMaxSize;

            if (toStore) {
                var modeKey = 'listViewMode' + this.scope;
                this.getStorage().set('state', modeKey, mode);
            }

            if (this.searchView && this.getView('search')) {
                this.getView('search').setViewMode(mode);
            }

            var methodName = 'setViewMode' + Espo.Utils.upperCaseFirst(this.viewMode);
            if (this[methodName]) {
                this[methodName]();
                return;
            }
        },

        setViewModeKanban: function () {
            this.collection.url = this.scope + '/action/listKanban';
            this.collection.maxSize = this.getConfig().get('recordsPerPageSmall');

            this.collection.sortBy = this.collection.defaultSortBy;
            this.collection.asc = this.collection.defaultAsc;
        },

        resetSorting: function () {
            this.collection.sortBy = this.defaultSortBy;
            this.collection.asc = this.defaultAsc;
            this.getStorage().clear('listSorting', this.collection.name);

            this.getStorage().clear('selectedNodeId', this.scope);
            this.getStorage().clear('selectedNodeRoute', this.scope);

            let treeView = this.getView('treePanel');
            if (treeView) {
                treeView.rebuildTree();
            }
        },

        getSearchDefaultData: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.defaultFilterData');
        },

        setupSearchManager: function () {
            var collection = this.collection;

            var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime(), this.getSearchDefaultData());
            searchManager.scope = this.scope;

            searchManager.loadStored();
            collection.where = searchManager.getWhere();
            this.searchManager = searchManager;
        },

        setupSorting: function () {
            if (!this.searchPanel) return;

            this.applyStoredSorting();
        },

        applyStoredSorting: function () {
            var sortingParams = this.getStorage().get('listSorting', this.collection.name) || {};
            if ('sortBy' in sortingParams) {
                this.collection.sortBy = sortingParams.sortBy;
            }
            if ('asc' in sortingParams) {
                this.collection.asc = sortingParams.asc;
            }
        },

        getRecordViewName: function () {
            if (this.viewMode === 'list') {
                return this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'list']) || this.recordView;
            }

            var propertyName = 'record' + Espo.Utils.upperCaseFirst(this.viewMode) + 'View';
            return this.getMetadata().get(['clientDefs', this.scope, 'recordViews', this.viewMode]) || this[propertyName];
        },

        afterRender: function () {
            this.createTreePanel();
            this.setupHeader();

            let treePanelView = this.getView('treePanel');

            this.collection.isFetched = false;
            this.clearView('list');

            if (treePanelView && this.getStorage().get('reSetupSearchManager', treePanelView.treeScope)) {
                this.getStorage().clear('reSetupSearchManager', treePanelView.treeScope);
                this.setupSearchManager();
            }

            if (!this.hasView('list')) {
                this.loadList();
            }

            let observer = new ResizeObserver(() => {
                if (treePanelView && this.previousWidth !== $('#content').width()) {
                    this.previousWidth = $('#content').width();
                    this.onTreeResize(treePanelView.$el.outerWidth());
                }
            });
            observer.observe($('#content').get(0));
        },

        loadList: function () {
            var methodName = 'loadList' + Espo.Utils.upperCaseFirst(this.viewMode);
            if (this[methodName]) {
                this[methodName]();
                return;
            }

            if (this.collection.isFetched) {
                this.createListRecordView(false);
            } else {
                Espo.Ui.notify(this.translate('loading', 'messages'));
                this.createListRecordView(true);
            }
        },

        prepareRecordViewOptions: function (options) {
        },

        createListRecordView: function (fetch) {
            var o = {
                collection: this.collection,
                el: this.options.el + ' .list-container',
                scope: this.scope,
                skipBuildRows: true
            };
            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);
            if (this.keepCurrentRootUrl) {
                o.keepCurrentRootUrl = true;
            }
            this.prepareRecordViewOptions(o);
            var listViewName = this.getRecordViewName();
            this.createView('list', listViewName, o, function (view) {
                if (!this.hasParentView()) {
                    view.undelegateEvents();
                    return;
                }

                this.listenToOnce(view, 'after:render', function () {
                    if (!this.hasParentView()) {
                        view.undelegateEvents();
                        this.clearView('list');
                    }
                    this.trigger('record-list-rendered', view)
                }, this);

                view.notify(false);
                if (this.searchPanel) {
                    this.listenTo(view, 'sort', function (obj) {
                        this.getStorage().set('listSorting', this.collection.name, obj);
                    }, this);
                }

                if (fetch) {
                    view.getSelectAttributeList(function (selectAttributeList) {
                        if (selectAttributeList) {
                            this.collection.data.select = selectAttributeList.join(',');
                        }
                        this.collection.fetch();
                    }.bind(this));
                } else {
                    view.render();
                }
            });
        },

        getHeader: function () {
            var headerIconHtml = this.getHeaderIconHtml();

            return this.buildHeaderHtml([
                headerIconHtml + this.getLanguage().translate(this.scope, 'scopeNamesPlural')
            ]);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate(this.scope, 'scopeNamesPlural'));
        },

        getCreateAttributes: function () {
        },

        prepareCreateReturnDispatchParams: function (params) {
        },

        actionQuickCreate: function () {
            var attributes = this.getCreateAttributes() || {};

            this.notify('Loading...');
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.edit') || 'views/modals/edit';
            var options = {
                scope: this.scope,
                attributes: attributes
            };
            if (this.keepCurrentRootUrl) {
                options.rootUrl = this.getRouter().getCurrentUrl();
            }

            var returnDispatchParams = {
                controller: this.scope,
                action: null,
                options: {
                    isReturn: true
                }
            };
            this.prepareCreateReturnDispatchParams(returnDispatchParams);
            _.extend(options, {
                returnUrl: this.getRouter().getCurrentUrl(),
                returnDispatchParams: returnDispatchParams
            });

            this.createView('quickCreate', 'views/modals/edit', options, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.collection.fetch();
                }, this);
            }.bind(this));
        },

        actionCreate: function () {
            var router = this.getRouter();

            var url = '#' + this.scope + '/create';
            var attributes = this.getCreateAttributes() || {};

            var options = {
                attributes: attributes
            };
            if (this.keepCurrentRootUrl) {
                options.rootUrl = this.getRouter().getCurrentUrl();
            }

            var returnDispatchParams = {
                controller: this.scope,
                action: null,
                options: {
                    isReturn: true
                }
            };
            this.prepareCreateReturnDispatchParams(returnDispatchParams);
            _.extend(options, {
                returnUrl: this.getRouter().getCurrentUrl(),
                returnDispatchParams: returnDispatchParams
            });

            router.navigate(url, {trigger: false});
            router.dispatch(this.scope, 'create', options);
        },

        isTreeAllowed() {
            return !this.getMetadata().get(['scopes', this.scope, 'leftSidebarDisabled'])
        },

        createTreePanel(scope) {
            if (!this.isTreeAllowed()) {
                return;
            }

            window.treePanelComponent = new Svelte.TreePanel({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                anchor: $(`${this.options.el} .tree-panel-anchor`).get(0),
                props: {
                    scope: scope ? scope : this.scope,
                    model: this.model,
                    collection: this.collection,
                    mode: 'list',
                    callbacks: {
                        selectNode: (data, force) => {
                            this.selectNode(data, force);
                        },
                        treeWidthChanged: (width) => {
                            this.onTreeResize(width)
                        },
                        treeReset: (treeScope) => {
                            this.treeReset(treeScope)
                        }
                    }
                }
            });

            if (this.getUser().isAdmin()) {
                this.createView('treeLayoutConfigurator', "views/record/layout-configurator", {
                    scope: this.scope,
                    viewType: 'leftSidebar',
                    layoutData: window.treePanelComponent.getLayoutData(),
                    el: $(`${this.options.el} .catalog-tree-panel .layout-editor-container`).get(0),
                }, (view) => {
                    view.on("refresh", () => {
                        window.treePanelComponent.refreshLayout()
                    })
                    view.render()
                })
            }

            this.listenTo(Backbone, 'after:search', collection => {
                if (this.collection.name === collection.name) {
                    if (window.treePanelComponent) {
                        window.treePanelComponent.handleCollectionSearch(collection)
                    }
                }
            });


            this.listenTo(this, 'record-list-rendered', (recordView) => {
                this.listenTo(recordView, `bookmarked-${this.scope}`, (_) => {
                    this.reloadBookmarks();
                });

                this.listenTo(recordView, `unbookmarked-${this.scope}`, (_) => {
                    this.reloadBookmarks();
                });
            });
        },

        modifyCollectionForSelectedNode() {
            const id = this.getStorage().get('selectedNodeId', this.scope);
            if (!id || ['_self', '_bookmark'].includes(this.getStorage().get('treeItem', this.scope))) {
                this.collection.whereAdditional = []
                return
            }

            this.collection.whereAdditional = [
                {
                    "type": "linkedWith",
                    "attribute": this.getStorage().get('treeItem', this.scope),
                    "value": [id]
                }
            ]

            this.collection.where = this.collection.where.filter(item => item['value']?.[0] !== "linkedWith" && item['attribute'] !== this.getStorage().get('treeItem', this.scope))
        },

        selectTreeNode() {
            const id = this.getStorage().get('selectedNodeId', this.scope);
            const route = this.parseRoute(this.getStorage().get('selectedNodeRoute', this.scope));

            if (window.treePanelComponent) {
                window.treePanelComponent.selectTreeNode(id, route);
            }

            this.notify('Please wait...');
            this.modifyCollectionForSelectedNode()

            this.collection.reset();
            this.collection.fetch().then(() => this.notify(false));
        },

        unSelectTreeNode(id) {
            if (window.treePanelComponent) {
                window.treePanelComponent.unSelectTreeNode(id);
            }

            this.notify('Please wait...');

            this.modifyCollectionForSelectedNode()

            this.collection.reset();
            this.collection.fetch().then(() => this.notify(false));
        },

        treeReset(treeScope) {
            this.notify('Please wait...');
            this.modifyCollectionForSelectedNode()

            if (![this.scope, 'Bookmark'].includes(treeScope)) {
                this.notify('Please wait...');
                this.collection.reset();
                this.collection.fetch().then(() => this.notify(false));
            }
        },

        selectNode(data, force = false) {
            if (['_self', '_bookmark'].includes(this.getStorage().get('treeItem', this.scope))) {
                if(data.click){
                    window.location.href = `/#${this.scope}/view/${data.id}`;
                }
                return;
            }

            if (!force && data.id === this.getStorage().get('selectedNodeId', this.scope)) {
                this.getStorage().clear('selectedNodeId', this.scope);
                this.getStorage().clear('selectedNodeRoute', this.scope);
                this.unSelectTreeNode(data.id);

                return;
            }

            this.getStorage().set('selectedNodeId', this.scope, data.id);
            if (data.route) {
                this.getStorage().set('selectedNodeRoute', this.scope, data.route);
            } else {
                this.getStorage().clear('selectedNodeRoute', this.scope);
            }

            this.selectTreeNode();
        },

        parseRoute(routeStr) {
            let route = [];
            (routeStr || '').split('|').forEach(item => {
                if (item) {
                    route.push(item);
                }
            });

            return route;
        },

        onTreeResize(width) {

        },

        reloadBookmarks() {
            if (window.treePanelComponent) {
                window.treePanelComponent.reloadBookmarks()
            }
        }

    });
});
