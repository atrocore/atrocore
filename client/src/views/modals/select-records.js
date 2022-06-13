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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('views/modals/select-records', ['views/modal', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        cssName: 'select-modal',

        multiple: false,

        header: false,

        template: 'modals/select-records',

        createButton: true,

        searchPanel: true,

        scope: null,

        noCreateScopeList: ['User', 'Team', 'Role', 'Portal'],

        className: 'dialog dialog-record',

        boolFilterData: {},

        disableSavePreset: false,

        layoutName: "listSmall",

        listLayout: null,

        searchView: 'views/record/search',

        selectedItems: [],

        data: function () {
            return {
                createButton: this.createButton,
                createText: this.translate('Create ' + this.scope, 'labels', this.scope),
                hasTree: this.isHierarchical()
            };
        },

        events: {
            'click button[data-action="create"]': function () {
                this.create();
            },
            'click .list a': function (e) {
                e.preventDefault();
            },
            'click .change-view': function (e) {
                let $current = $(e.currentTarget);

                $('a.change-view').removeClass('btn-primary').addClass('btn-default');
                $current.removeClass('btn-default').addClass('btn-primary');

                this.getStorage().set('list-small-view-type', this.scope, $current.data('view'));

                this.toggleViewType();
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

            if ('multiple' in this.options) {
                this.multiple = this.options.multiple;
            }

            if (this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy' && this.getMetadata().get(`scopes.${this.scope}.multiParents`) !== true) {
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

            if (this.multiple) {
                this.buttonList.unshift({
                    name: 'select',
                    style: 'primary',
                    label: 'Select',
                    disabled: true,
                    onClick: function (dialog) {
                        if (this.getSelectedViewType() === 'tree') {
                            let ids = [];
                            this.selectedItems.forEach(id => {
                                ids.push({id: id});
                            });
                            this.trigger('select', ids);
                        } else {
                            let listView = this.getView('list');
                            if (listView.allResultIsChecked) {
                                var where = this.collection.where;
                                this.trigger('select', {
                                    massRelate: true,
                                    where: where
                                });
                            } else {
                                var list = listView.getSelected();
                                if (list.length) {
                                    this.trigger('select', list);
                                }
                            }
                        }
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
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;
                this.collection = collection;

                this.defaultSortBy = collection.sortBy;
                this.defaultAsc = collection.asc;

                this.loadSearch();
                this.wait(true);
                this.loadList();
            }, this);

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
                    boolFilterData: this.boolFilterData
                }, function (view) {
                    view.render();
                    this.listenTo(view, 'reset', function () {
                        this.collection.sortBy = this.defaultSortBy;
                        this.collection.asc = this.defaultAsc;
                    }, this);
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
                checkAllResultDisabled: !this.massRelateEnabled,
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
                        } else {
                            this.disableButton('select');
                        }
                    }, this);
                    this.listenTo(view, 'select-all-results', function () {
                        this.enableButton('select');
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

            if (this.isHierarchical()) {
                let treeButtonClass = 'btn-primary';
                let tableButtonClass = 'btn-default';

                if (this.getSelectedViewType() === 'list') {
                    treeButtonClass = 'btn-default';
                    tableButtonClass = 'btn-primary';
                }

                let html = '<div class="btn-group main-btn-group pull-right">';
                html += '<div class="page-header" style="margin-top: 0">';
                html += '<div class="header-buttons"><div class="header-items">';
                html += `<a href="javascript:" class="btn action ${treeButtonClass} change-view action" data-view="tree"><span class="fa fa-sitemap"></span></a>`
                html += `<a href="javascript:" class="btn action ${tableButtonClass} change-view action" data-view="list"><span class="fa fa-th-list"></span></a>`;
                html += '</div></div></div>';
                this.$el.find('.modal-footer').append(html);

                this.setupTree();
                this.toggleViewType();
            }
        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy';
        },

        getSelectedViewType() {
            return this.getStorage().get('list-small-view-type', this.scope) || 'tree';
        },

        setupTree() {
            const $tree = this.$el.find('.records-tree');
            $tree.tree('destroy');
            $tree.tree({
                dataUrl: this.scope + '/action/Tree',
                selectable: true,
                dragAndDrop: false,
                useContextMenu: false,
                closedIcon: $('<i class="fa fa-angle-right"></i>'),
                openedIcon: $('<i class="fa fa-angle-down"></i>'),
            }).on('tree.click', e => {
                if (this.multiple) {
                    e.preventDefault();
                    let selected_node = e.node;
                    if ($tree.tree('isNodeSelected', selected_node)) {
                        $tree.tree('removeFromSelection', selected_node);
                    } else {
                        $tree.tree('addToSelection', selected_node);
                    }
                }

                let nodes = $tree.tree('getSelectedNodes') || [];

                this.selectedItems = [];
                nodes.forEach(node => {
                    this.selectedItems.push(node.id);
                });

                if (this.selectedItems.length) {
                    this.enableButton('select');
                } else {
                    this.disableButton('select');
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
    });
});

