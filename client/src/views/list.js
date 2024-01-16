/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

Espo.define('views/list', ['views/main', 'search-manager'], function (Dep, SearchManager) {

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

        init: function () {
            Dep.prototype.init.call(this);

            if (this.options.params.viewMode) {
                this.viewMode = this.options.params.viewMode;
            }
        },

        setup: function () {
            this.collection.maxSize = this.getConfig().get('recordsPerPage') || this.collection.maxSize;

            this.collectionUrl = this.collection.url;
            this.collectionMaxSize = this.collection.maxSize;

            this.setupModes();

            this.setViewMode(this.viewMode);

            if (this.getMetadata().get('clientDefs.' + this.scope + '.searchPanelDisabled')) {
                this.searchPanel = false;
            }

            if (this.getMetadata().get(['clientDefs', this.scope, 'createDisabled'])) {
                this.createButton = false;
            }

            this.entityType = this.collection.name;

            this.headerView = this.options.headerView || this.headerView;
            this.recordView = this.options.recordView || this.recordView;
            this.searchView = this.options.searchView || this.searchView;

            this.setupHeader();

            if (this.searchPanel) {
                this.setupSearchManager();
            }

            this.defaultSortBy = this.collection.sortBy;
            this.defaultAsc = this.collection.asc;

            this.setupSorting();

            if (this.createButton) {
                this.setupCreateButton();
            }

            if (this.getMetadata().get(['clientDefs', this.scope, 'kanbanViewMode'])) {
                let buttonKanban = {
                    link: '#' + this.scope + '/kanban',
                    name: 'kanban',
                    title: 'Kanban',
                    acl: 'read',
                    iconHtml: '<span class="fa fa-grip-horizontal"></span>'
                };

                let listIndex = this.menu.buttons.findIndex(button => button.name === 'list');
                if (listIndex > -1) {
                    this.menu.buttons.splice(listIndex, 0, buttonKanban);
                } else {
                    this.menu.buttons.unshift({
                        link: '#' + this.scope + '/list',
                        name: 'list',
                        title: 'List',
                        acl: 'read',
                        iconHtml: '<span class=\"fa fa-list\"></span>'
                    });
                    this.menu.buttons.unshift(buttonKanban);
                }
            }

            (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicEntityActions']) || []).forEach(dynamicAction => {
                if (this.getAcl().check(dynamicAction.acl.scope, dynamicAction.acl.action)) {
                    if (dynamicAction.display === 'dropdown') {
                        (this.menu.dropdown || []).push({
                            label: dynamicAction.name,
                            action: "dynamicEntityAction",
                            iconHtml: '',
                            data:{
                                id: dynamicAction.id
                            }
                        });
                    }

                    if (dynamicAction.display === 'single') {
                        (this.menu.buttons || []).unshift({
                            label: dynamicAction.name,
                            action: "dynamicEntityAction",
                            iconHtml: '',
                            data:{
                                id: dynamicAction.id
                            }
                        });
                    }
                }
            });

            this.getStorage().set('list-view', this.scope, this.viewMode);
        },

        actionDynamicEntityAction(data) {
            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Action/action/executeNow', {actionId: data.id}).success(response => {
                if (response.inBackground) {
                    this.notify(this.translate('jobAdded', 'messages'), 'success');
                } else {
                    this.notify('Done', 'success');
                }
            });
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

            if (this.viewModeList.length > 1) {
                this.viewMode = null;
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

        setupHeader: function () {
            this.createView('header', this.headerView, {
                collection: this.collection,
                el: '#main > .page-header',
                scope: this.scope,
                isXsSingleRow: true
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
            this.createView('search', this.searchView, {
                collection: this.collection,
                el: '#main > .page-header .row .search-container',
                searchManager: this.searchManager,
                scope: this.scope,
                viewMode: this.viewMode,
                viewModeList: this.viewModeList
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
            if (!this.hasView('list')) {
                this.loadList();
            }

            if (this.searchPanel) {
                this.setupSearchPanel();
            }

            let mode = this.getStorage().get('list-view', this.scope);
            let button = $('a[data-name="' + mode + '"]');
            if (button.length) {
                if (button.hasClass('btn-default')) {
                    button.removeClass('btn-default');
                }
                button.addClass('btn-primary');
            }
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
        }

    });
});
