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

Espo.define('views/modals/select-records', ['views/modal', 'search-manager', 'lib!JsTree'], function (Dep, SearchManager) {

    return Dep.extend({

        cssName: 'select-modal',

        multiple: false,

        header: false,

        template: 'modals/select-records',

        createButton: true,

        searchPanel: true,

        scope: null,

        noCreateScopeList: ['User', 'Team', 'Role'],

        className: 'dialog dialog-record dialog-select-record',

        boolFilterData: {},

        disableSavePreset: false,

        layoutName: "list",

        listLayout: null,

        searchView: 'views/record/search',

        selectedItems: [],

        selectedItemsNames: {},

        lastTextFilter: null,

        offsets: {},

        currentNode: null,

        maxSize: null,

        fullHeight: true,

        data: function () {
            return {
                hasTree: this.isHierarchical(),
                hasTotalCount: this.getConfig().get('displayListViewRecordCount') && this.multiple,
                totalCount: this.collection.total,
            };
        },

        events: {
            'click [data-action="create"]': function () {
                this.create();
            },
            'click .list a': function (e) {
                e.preventDefault();
            },
            'click .change-view': function (e) {
                this.trigger('change-view', e);
            },
        },

        setup: function () {
            this.boolFilterData = this.options.boolFilterData || this.boolFilterData;
            this.disableSavePreset = this.options.disableSavePreset || this.disableSavePreset;
            this.layoutName = this.options.layoutName || this.layoutName;
            this.listLayout = this.options.listLayout || this.listLayout;
            this.rowActionsDisabled = this.options.rowActionsDisabled || this.rowActionsDisabled;

            this.filters = this.options.filters || {};
            this.boolFilterList = this.options.boolFilterList || [];
            this.primaryFilterName = this.options.primaryFilterName || null;
            this.scope = this.entityType = this.options.scope || this.scope;
            this.selectDuplicateEnabled = !!this.options.selectDuplicateEnabled

            if ('multiple' in this.options) {
                this.multiple = this.options.multiple;

            }

            if (this.isHierarchical() && this.getMetadata().get(`scopes.${this.scope}.multiParents`) !== true) {
                if (this.options.boolFilterList && this.options.boolFilterList.includes('notChildren')) {
                    this.multiple = false;
                }
            }

            if ('createButton' in this.options) {
                this.createButton = this.options.createButton;
            }

            this.massRelateEnabled = this.options.massRelateEnabled;

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            if (this.selectDuplicateEnabled) {
                this.buttonList.unshift({
                    name: 'selectDuplicate',
                    style: 'primary',
                    label: 'Duplicate and Select',
                    disabled: true,
                    onClick: function (dialog) {
                        this.handleOnSelect(true);
                        dialog.close();
                    }.bind(this),
                });
            }

            if (this.multiple) {
                this.buttonList.unshift({
                    name: 'select',
                    style: 'primary',
                    label: 'Select',
                    disabled: true,
                    onClick: function (dialog) {
                        this.handleOnSelect();
                        dialog.close();
                    }.bind(this),
                });
            }

            if (this.noCreateScopeList.indexOf(this.scope) !== -1) {
                this.createButton = false;
            }

            if (this.createButton) {
                if (
                    !this.getAcl().check(this.scope, 'create')
                    ||
                    this.getMetadata().get(['clientDefs', this.scope, 'createDisabled'])
                ) {
                    this.createButton = false;
                }
            }

            this.header = '';
            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            this.header += this.getLanguage().translate(this.scope, 'scopeNamesPlural');
            this.header = iconHtml + this.header;

            this.waitForView('list');
            if (this.searchPanel) {
                this.waitForView('search');
            }

            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = this.getMetadata().get(`clientDefs.${this.scope}.limit`) || this.getConfig().get('recordsPerPageSmall') || 5;
                this.collection = collection;

                this.defaultSortBy = collection.sortBy;
                this.defaultAsc = collection.asc;

                this.loadSearch();
                this.wait(true);
                this.loadList();
            }, this);


            this.maxSize = this.getConfig().get('recordsPerPage', 200);

            this.listenTo(this.collection, 'sync', () => {
                this.findInTree();
            });

            this.listenTo(this.collection, 'update-total', () => {
                this.$el.find('.for-tree-view .total-count-span').html(this.collection.total);
                this.$el.find('.for-tree-view .shown-count-span').html(this.collection.total);
            })
        },

        changeView(e) {
            let $current = $(e.currentTarget);

            $('a.change-view').removeClass('btn-primary').addClass('btn-default');
            $current.removeClass('btn-default').addClass('btn-primary');

            this.getStorage().set('list-small-view-type', this.scope, $current.data('view'));

            // refresh tree selections
            this.selectedItems = [];
            this.selectedItemsNames = {};
            this.setupTree();

            // refresh list selections
            this.$el.find('.checkbox-all:checked').click();
            this.$el.find('input.record-checkbox:checked').click();

            this.disableButton('select');

            this.toggleViewType();
        },

        loadSearch: function () {
            var searchManager = this.searchManager = new SearchManager(this.collection, 'listSelect', null, this.getDateTime());
            searchManager.emptyOnReset = true;
            if (this.filters) {
                searchManager.setAdvanced(this.filters);
            }

            var boolFilterList = this.boolFilterList || this.getMetadata().get('clientDefs.' + this.scope + '.selectDefaultFilters.boolFilterList');
            if (boolFilterList) {
                var d = {};
                boolFilterList.forEach(function (item) {
                    d[item] = true;
                });
                searchManager.setBool(d);
            }
            var primaryFilterName = this.primaryFilterName || this.getMetadata().get('clientDefs.' + this.scope + '.selectDefaultFilters.filter');
            if (primaryFilterName) {
                searchManager.setPrimary(primaryFilterName);
            }

            let where = searchManager.getWhere();
            where.forEach(item => {
                if (item.type === 'bool') {
                    let data = {};
                    item.value.forEach(elem => {
                        if (elem in this.boolFilterData) {
                            data[elem] = this.boolFilterData[elem];
                        }
                    });
                    item.data = data;
                }
            });
            this.collection.where = where;

            this.collection.whereAdditional = this.options.whereAdditional || [];

            if (this.searchPanel) {
                let hiddenBoolFilterList = this.getMetadata().get(`clientDefs.${this.scope}.hiddenBoolFilterList`) || [];
                let searchView = this.getMetadata().get(`clientDefs.${this.scope}.recordViews.search`) || this.searchView;

                this.createView('search', searchView, {
                    collection: this.collection,
                    el: this.containerSelector + ' .search-container',
                    searchManager: searchManager,
                    disableSavePreset: this.disableSavePreset,
                    hiddenBoolFilterList: hiddenBoolFilterList,
                    boolFilterData: this.boolFilterData,
                    selectRecordsView: this,
                }, function (view) {
                    view.render();
                    this.listenTo(view, 'reset', function () {
                        this.collection.sortBy = this.defaultSortBy;
                        this.collection.asc = this.defaultAsc;
                    }, this);

                    this.listenTo(this, 'change-view', e => {
                        view.resetFilters();
                        this.changeView(e);
                    });

                    this.listenTo(view, 'after:render', e => {
                        view.toggleSearchFilters(this.getSelectedViewType());
                    });

                    this.listenTo(this, 'after:toggleViewType', viewType => {
                        view.toggleSearchFilters(viewType);
                    });
                });
            }
        },

        loadList: function () {
            let viewName = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.listSelect') ||
                this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') ||
                'views/record/list';

            this.createView('list', viewName, {
                collection: this.collection,
                el: this.containerSelector + ' .list-container',
                selectable: true,
                checkboxes: this.multiple,
                massActionsDisabled: true,
                rowActionsView: false,
                layoutName: this.layoutName,
                searchManager: this.searchManager,
                buttonsDisabled: true,
                skipBuildRows: true
            }, function (view) {
                this.listenTo(view, 'select', function (model) {
                    this.trigger('select', model);
                    this.close();
                }.bind(this));

                if (this.multiple) {
                    this.listenTo(view, 'check', function () {
                        if (view.checkedList.length) {
                            this.enableButton('select');
                            this.enableButton('selectDuplicate');
                        } else {
                            this.disableButton('select');
                            this.disableButton('selectDuplicate');
                        }
                    }, this);
                    this.listenTo(view, 'select-all-results', function () {
                        this.enableButton('select');
                        this.enableButton('selectDuplicate');
                    }, this);
                }

                if (this.options.forceSelectAllAttributes || this.forceSelectAllAttributes) {
                    this.listenToOnce(view, 'after:build-rows', function () {
                        this.wait(false);
                    }, this);
                    this.collection.fetch();
                } else {
                    view.getSelectAttributeList(function (selectAttributeList) {
                        if (!~selectAttributeList.indexOf('name')) {
                            selectAttributeList.push('name');
                        }

                        var mandatorySelectAttributeList = this.options.mandatorySelectAttributeList || this.mandatorySelectAttributeList || [];
                        mandatorySelectAttributeList.forEach(function (attribute) {
                            if (!~selectAttributeList.indexOf(attribute)) {
                                selectAttributeList.push(attribute);
                            }
                        }, this);

                        if (selectAttributeList) {
                            this.collection.data.select = selectAttributeList.join(',');
                        }
                        this.listenToOnce(view, 'after:build-rows', function () {
                            this.wait(false);
                        }, this);
                        this.collection.fetch();
                    }.bind(this));
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let html = ''

            if (this.isHierarchical()) {
                let treeButtonClass = 'btn-primary';
                let tableButtonClass = 'btn-default';

                if (this.getSelectedViewType() === 'list') {
                    treeButtonClass = 'btn-default';
                    tableButtonClass = 'btn-primary';
                }

                html += `<a href="javascript:" class="btn action ${treeButtonClass} change-view action" data-view="tree"><span class="fa fa-stream"></span></a>`
                html += `<a href="javascript:" class="btn action ${tableButtonClass} change-view action" data-view="list"><svg class="icon"><use href="client/img/icons/icons.svg#th-list"></use></svg></a>`;

                this.setupTree();
                this.toggleViewType();

                const modalBody = this.$el.find('.modal-body');
                if (modalBody.length) {
                    modalBody.off('scroll');
                    modalBody.on('scroll', function () {
                        if (this.getSelectedViewType() === 'tree' && modalBody.outerHeight() + modalBody.scrollTop() >= modalBody.get(0).scrollHeight - 50) {
                            const btnMore = modalBody.find('.jqtree-tree > .show-more span');

                            if (btnMore.length) {
                                btnMore.click();
                            }
                        }
                    }.bind(this));
                }
            }

            if (this.createButton) {
                let buttonLabel = this.translate('Create ' + this.scope, 'labels', this.scope);
                html += `<a href="javascript:" data-action="create" ${html ? 'style="margin-left: 15px"' : ''} class="btn action btn-primary">${buttonLabel}</a>`
            }

            if (html) {
                this.$el.find('.modal-footer').append(`<div class="btn-group main-btn-group pull-right">
                    <div class="page-header" style="margin-top: 0">
                    <div class="header-buttons"><div class="header-items">${html}</div></div>
                    </div>
                    </div>`
                );
            }

        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy'
                && this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`) !== true;
        },

        getSelectedViewType() {
            if (!this.isHierarchical()) {
                return 'list';
            }
            return this.getStorage().get('list-small-view-type', this.scope) || 'tree';
        },

        findInTree() {
            if (this.getSelectedViewType() !== 'tree') {
                return;
            }

            let textFilter = null;
            if (this.collection.where) {
                this.collection.where.forEach(item => {
                    if (item.type && item.type === 'textFilter') {
                        textFilter = item.value;
                    }
                });
            }

            if (textFilter === this.lastTextFilter) {
                return;
            }

            this.lastTextFilter = textFilter;

            let $shown = this.$el.find('.for-tree-view .shown-count-span');
            let $total = this.$el.find('.for-tree-view .total-count-span');

            $shown.parent().show();

            if (textFilter === null) {
                $shown.html(this.collection.total);
                $total.html(this.collection.total);
                this.setupTree();
                return;
            }

            if (this.collection.total === 0) {
                $shown.parent().hide();
                this.setupTree([]);
                return;
            }

            let ids = [];
            this.collection.models.forEach(model => {
                ids.push(model.get('id'));
            });

            this.ajaxGetRequest(`${this.scope}/action/TreeData`, {ids: ids}).then(response => {
                $shown.html(response.total);
                $total.html(response.total);
                this.setupTree(response.tree);
            });
        },

        generateUrl(node) {
            let queryParameters = {
                sortBy: this.collection.sortBy,
                asc: this.collection.asc,
                where: this.collection.getWhere(),
            };
            let url = this.scope + '/action/Tree?' + $.param(queryParameters);
            let id = 'root';

            if (node && node.id) {
                url += '&node=' + node.id;
                id = node.id;
            }

            if (!(id in this.offsets)) {
                this.offsets[id] = 0;
            }
            url += '&offset=' + this.offsets[id] + '&maxSize=' + this.maxSize;

            return url;
        },

        loadMore(node, previous) {
            this.ajaxGetRequest(this.generateUrl(node)).then(function (response) {
                if (response['list']) {
                    const id = node ? node.id : 'root';
                    response['list'] = this.filterResponse(id, response);
                    const tree = this.$el.find('.records-tree');

                    response['list'].reverse().forEach(item => {
                        tree.tree('addNodeAfter', item, previous)
                    });
                }
            }.bind(this));
        },

        filterResponse(id, response) {
            let offset = this.offsets[id] || 0;

            if (offset + this.maxSize < response['total']) {
                this.offsets[id] += this.maxSize;

                response['list'].push({
                    id: 'show-more',
                    name: this.getLanguage().translate('Show more')
                });
            }

            return response['list'];
        },

        isRootNode(node) {
            return !node.parent.getLevel();
        },

        setupTree(data) {
            if (this.getSelectedViewType() !== 'tree') {
                return;
            }
            this.offsets = {root: 0};

            const $tree = this.$el.find('.records-tree');
            let treeData = {
                selectable: true,
                dragAndDrop: false,
                useContextMenu: false,
                closedIcon: $('<i class="fa fa-angle-right"></i>'),
                openedIcon: $('<i class="fa fa-angle-down"></i>'),
                onCreateLi: function (node, $li, isSelected) {
                    if (node.disabled) {
                        $li.addClass('disabled');
                    } else {
                        $li.removeClass('disabled');
                    }

                    let search = $('.search-container .text-filter').val();
                    if (search.length > 0) {
                        search = search.replace(/\*/g, '');
                        if (search.length > 0) {
                            let $title = $li.find('.jqtree-title');
                            let name = $title.html();
                            let matches = name.match(new RegExp(search, 'ig'));
                            if (matches) {
                                let processed = [];
                                matches.forEach(v => {
                                    if (!processed.includes(v)) {
                                        processed.push(v);
                                        $title.html(name.replace(new RegExp(v, 'g'), `<b>${v}</b>`));
                                    }
                                });
                            }
                        }
                    }

                    if (node.id === 'show-more') {
                        $li.removeClass('jqtree-selected');

                        if (this.isRootNode(node)) {
                            $li.addClass('show-more');
                            $li.find('.jqtree-element').addClass('btn btn-default btn-block');
                            $li.find('.jqtree-title').addClass('more-label');
                        }
                    }
                }.bind(this)
            };
            if (data) {
                treeData['data'] = data;
                treeData['autoOpen'] = true;
            } else {
                treeData['dataUrl'] = function (node) {
                    this.currentNode = node;

                    return this.generateUrl(node);
                }.bind(this);
                treeData['dataFilter'] = function (response) {
                    const currentNode = this.currentNode ? this.currentNode.id : 'root';

                    return this.filterResponse(currentNode, response);
                }.bind(this);
            }

            $tree.tree('destroy');
            $tree.tree(treeData).on('tree.click', e => {
                if (e.node.disabled) {
                    e.preventDefault();
                    return false;
                }

                let selected_node = e.node;
                this.currentNode = null;

                if (selected_node.id === 'show-more') {
                    const previous = selected_node.getPreviousSibling(),
                        parent = selected_node.parent && selected_node.parent.id ? selected_node.parent : null;

                    this.$el.find('.records-tree').tree('removeNode', selected_node);
                    selected_node = null;
                    return this.loadMore(parent, previous);
                }

                this.offsets = {root: 0};

                if (this.multiple) {
                    e.preventDefault();

                    if ($tree.tree('isNodeSelected', selected_node)) {
                        $tree.tree('removeFromSelection', selected_node);
                    } else {
                        $tree.tree('addToSelection', selected_node);
                    }
                    this.selectedItems = [];
                    ($tree.tree('getSelectedNodes') || []).forEach(node => {
                        this.selectedItems.push(node.id);
                        this.selectedItemsNames[node.id] = node.name;
                    });

                    if (this.selectedItems.length) {
                        this.enableButton('select');
                        this.enableButton('selectDuplicate');
                    } else {
                        this.disableButton('select');
                        this.disableButton('selectDuplicate');
                    }
                } else {
                    this.getModelFactory().create(this.scope, model => {
                        model.set('id', e.node.id);
                        model.set('name', e.node.name);
                        model.fetch().then(() => {
                            this.trigger('select', model);
                            this.close();
                        })
                    });
                }
            });
        },

        toggleViewType() {
            let viewType = this.getSelectedViewType();

            if (viewType === 'tree') {
                this.$el.find('.for-table-view').hide();
                this.$el.find('.for-tree-view').show();
            } else {
                this.$el.find('.for-table-view').show();
                this.$el.find('.for-tree-view').hide();
            }

            this.trigger('after:toggleViewType', viewType);
        },

        create: function () {
            var self = this;

            this.notify('Loading...');
            this.createView('quickCreate', 'views/modals/edit', {
                scope: this.scope,
                fullFormDisabled: true,
                attributes: this.options.createAttributes,
            }, function (view) {
                view.once('after:render', function () {
                    self.notify(false);
                });
                view.render();

                self.listenToOnce(view, 'leave', function () {
                    view.close();
                    self.close();
                });
                self.listenToOnce(view, 'after:save', function (model) {
                    view.close();
                    self.trigger('select', model);
                    setTimeout(function () {
                        self.close();
                    }, 10);

                }.bind(this));
            });
        },
        handleOnSelect(duplicate = false) {
            if (this.getSelectedViewType() === 'tree') {
                let ids = [];
                this.selectedItems.forEach(id => {
                    ids.push({id: id, name: this.selectedItemsNames[id]});
                });
                this.trigger('select', ids, duplicate);
            } else {
                let listView = this.getView('list');
                if (listView.allResultIsChecked) {
                    var where = this.collection.where;
                    this.trigger('select', {
                        massRelate: true,
                        where: where
                    }, duplicate);
                } else {
                    var list = listView.getSelected();
                    if (list.length) {
                        this.trigger('select', list, duplicate);
                    }
                }
            }
        }
    });
});

