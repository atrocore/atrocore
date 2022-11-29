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

Espo.define('views/record/panels/tree-panel', ['view', 'lib!JsTree'],
    Dep => Dep.extend({

        template: 'record/tree-panel',

        minWidth: 200,

        maxWidth: 600,

        currentWidth: null,

        offsets: {},

        currentNode: null,

        maxSize: null,

        events: {
            'click button[data-action="collapsePanel"]': function () {
                this.actionCollapsePanel();
            },

            'click .reset-tree-filter': function (e) {
                e.preventDefault();
                this.trigger('tree-reset');
            }
        },

        setup() {
            this.scope = this.options.scope || this.scope;
            this.treeScope = this.scope;

            let treeScopes = this.getMetadata().get(`clientDefs.${this.scope}.treeScopes`);
            if (treeScopes) {
                this.treeScope = this.getStorage().get('treeScope', this.scope) || treeScopes[0];
            }

            if (this.options.collection) {
                this.listenTo(this.options.collection, 'sync', () => {
                    if (this.options.collection.name === this.treeScope) {
                        this.getStorage().set('treeWhereData', this.treeScope, this.options.collection.where);
                        this.buildTree();
                    }
                });
            }

            this.wait(true);
            this.buildSearch();
            this.wait(false);

            this.currentWidth = this.getStorage().get('panelWidth', this.scope) || this.minWidth;

            this.maxSize = this.getConfig().get('recordsPerPage', 200);
        },

        data() {
            return {
                scope: this.scope,
                treeScope: this.treeScope
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.buildTree();

            this.actionCollapsePanel('open');
            if ($(window).width() <= 767 || !!this.getStorage().get('catalog-tree-panel', this.scope)) {
                this.actionCollapsePanel();
            }

            this.treePanelResize();

            $(window).on('resize load', () => {
                this.treePanelResize()
            });

            this.$el.off('scroll');
            this.$el.on('scroll', function () {
                if (this.$el.outerHeight() + this.$el.scrollTop() >= this.$el.get(0).scrollHeight - 50) {
                    const btnMore = this.$el.find('.jqtree-tree > .show-more span[data-id="show-more"]');

                    if (btnMore.length) {
                        btnMore.click();
                    }
                }
            }.bind(this))

            this.toggleVisibilityForResetButton();
        },

        toggleVisibilityForResetButton() {
            let $reset = this.$el.find('.reset-search-in-tree-button');
            if (this.getStorage().get('treeSearchValue', this.treeScope)) {
                $reset.show();
            } else {
                $reset.hide();
            }
        },

        treePanelResize() {
            if ($(window).width() >= 768) {
                const resizer = this.$el.find('.category-panel-resizer');

                if (resizer) {
                    resizer.off('mousedown');
                    resizer.off('mouseup');

                    if (!this.$el.hasClass('catalog-tree-panel-hidden')) {
                        this.trigger('tree-width-changed', this.currentWidth);
                        $(window).trigger('tree-width-changed', this.currentWidth);

                        this.$el.css('width', this.currentWidth + 'px');

                        // click on resize bar
                        resizer.mousedown(function (e) {
                            let initPositionX = e.pageX;
                            let initWidth = this.$el.outerWidth();

                            // change tree panel width
                            $('body').mousemove(function (event) {
                                let positionX = event.pageX;

                                // if horizontal mouse move
                                if (initPositionX !== positionX) {
                                    let width = initWidth + (positionX - initPositionX);

                                    if (width >= this.minWidth && width <= this.maxWidth) {
                                        this.currentWidth = width;

                                        this.trigger('tree-width-changed', this.currentWidth);
                                        $(window).trigger('tree-width-changed', this.currentWidth);

                                        this.$el.css('width', this.currentWidth + 'px');
                                    }
                                }
                            }.bind(this));
                        }.bind(this));

                        // setup new width
                        resizer.add('body').mouseup(function () {
                            if (this.currentWidth) {
                                this.getStorage().set('panelWidth', this.scope, this.currentWidth)
                            }

                            $('body').off('mousemove');
                        }.bind(this));
                    } else {
                        this.$el.css('width', 'unset');

                        this.trigger('tree-width-changed', this.$el.outerWidth());
                        $(window).trigger('tree-width-changed', this.currentWidth);
                    }
                }
            } else {
                this.trigger('tree-width-unset');
            }
        },

        openNodes($tree, ids) {
            let result = false

            ids.forEach(item => {
                let $els = $tree.find(`.jqtree-title[data-id="${item}"]`);
                if ($els.length > 0) {
                    $els.each((k, el) => {
                        let $el = $(el);
                        let $li = $el.parent().parent();
                        if ($li.hasClass('jqtree-closed')) {
                            result = true;
                            let node = $tree.tree('getNodeByHtmlElement', $el);
                            $tree.tree('openNode', node, false);
                        }
                    });
                }
            });

            return result;
        },

        selectTreeNode(ids, id) {
            const locationHash = window.location.hash;
            const $tree = this.getTreeEl();
            let interval = setInterval(() => {
                if (!this.openNodes($tree, ids) || locationHash !== window.location.hash) {
                    clearInterval(interval);
                }

                let node = $tree.tree('getNodeById', id);
                if (node) {
                    $tree.tree('addToSelection', node);
                }

                $tree.find(`.jqtree-title`).each((k, el) => {
                    let $el = $(el);
                    let $li = $el.parent().parent();

                    if ($el.data('id') !== id) {
                        $tree.tree('removeFromSelection', $tree.tree('getNodeById', $el.data('id')));
                        $li.removeClass('jqtree-selected');
                    } else if (!$li.hasClass('jqtree-selected')) {
                        $li.addClass('jqtree-selected');
                    }
                });
            }, 500);
        },

        generateUrl(node) {
            let url = this.treeScope + '/action/Tree?isTreePanel=1';
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
                    const tree = this.getTreeEl();

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

        buildTree() {
            let data = null;

            let whereData = this.getStorage().get('treeWhereData', this.treeScope) || [];

            let searchValue = this.getStorage().get('treeSearchValue', this.treeScope) || null;
            if (searchValue) {
                $('.search-in-tree-input').val(searchValue);
                whereData = [{"type": "textFilter", "value": searchValue}];
            }

            if (whereData.length > 0) {
                this.ajaxGetRequest(this.treeScope, {
                    "select": "id,name",
                    "offset": 0,
                    "maxSize": 2000,
                    "sortBy": "id",
                    "asc": true,
                    "where": whereData
                }, {async: false}).then(response => {
                    let ids = [];
                    if (response.list) {
                        response.list.forEach(record => {
                            ids.push(record.id);
                        });
                    }
                    this.ajaxGetRequest(`${this.treeScope}/action/TreeData`, {"ids": ids}, {async: false}).then(response => {
                        data = response.tree;
                    });
                });
            }

            let treeData = {
                dataUrl: function (node) {
                    this.currentNode = node;
                    return this.generateUrl(node);
                }.bind(this),
                dataFilter: function (response) {
                    const currentNode = this.currentNode ? this.currentNode.id : 'root';
                    return this.filterResponse(currentNode, response);
                }.bind(this),
                selectable: true,
                dragAndDrop: this.getMetadata().get(`scopes.${this.treeScope}.multiParents`) !== true,
                useContextMenu: false,
                closedIcon: $('<i class="fa fa-angle-right"></i>'),
                openedIcon: $('<i class="fa fa-angle-down"></i>'),
                onCreateLi: function (node, $li, is_selected) {
                    if (node.disabled) {
                        $li.addClass('disabled');
                    } else {
                        $li.removeClass('disabled');
                    }

                    const $title = $li.find('.jqtree-title');

                    /**
                     * Mark search str
                     */
                    if (searchValue) {
                        let search = searchValue.replace(/\*/g, '');
                        if (search.length > 0) {
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

                    if (whereData.length > 0 && this.getStorage().get('treeScope', this.scope) === this.scope && this.model && this.model.get('id') === node.id) {
                        $tree.tree('addToSelection', node);
                        $li.addClass('jqtree-selected');
                    }

                    $title.attr('data-id', node.id);

                    if (this.getMetadata().get(`scopes.${this.treeScope}.multiParents`) !== true && treeData.dragAndDrop) {
                        $title.attr('title', this.translate("useDragAndDrop"));
                    }

                    if (node.id === 'show-more' && this.isRootNode(node)) {
                        $li.addClass('show-more');
                        $li.find('.jqtree-element').addClass('btn btn-default btn-block');
                        $li.find('.jqtree-title').addClass('more-label');
                    }
                }.bind(this)
            };

            if (data) {
                treeData['data'] = data;
                treeData['autoOpen'] = true;
                treeData['dragAndDrop'] = false;

                delete treeData['dataUrl'];
                delete treeData['dataFilter'];
            }

            const $tree = this.getTreeEl();
            this.offsets = {root: 0};

            $tree.tree('destroy');
            $tree.tree(treeData).on('tree.init', () => {
                    this.trigger('tree-init');
                }
            ).on('tree.move', e => {
                e.preventDefault();

                const parentName = this.treeScope === 'Category' ? 'categoryParent' : 'parent';

                let moveInfo = e.move_info;
                let data = {
                    _position: moveInfo.position,
                    _target: moveInfo.target_node.id
                };

                data[parentName + 'Id'] = null;
                data[parentName + 'Name'] = null;

                if (moveInfo.position === 'inside') {
                    data[parentName + 'Id'] = moveInfo.target_node.id;
                    data[parentName + 'Name'] = moveInfo.target_node.name;
                } else if (moveInfo.target_node.parent.id) {
                    data[parentName + 'Id'] = moveInfo.target_node.parent.id
                    data[parentName + 'Name'] = moveInfo.target_node.parent.name;
                }

                this.ajaxPatchRequest(`${this.treeScope}/${moveInfo.moved_node.id}`, data).success(response => {
                    moveInfo.do_move();
                    if (this.model) {
                        this.model.fetch();
                        $('.action[data-action=refresh]').click();
                    }
                });
            }).on('tree.click', e => {
                e.preventDefault();
                if (e.node.disabled) {
                    return false;
                }

                this.currentNode = null;

                const $el = $(e.click_event.target);
                if ($el.hasClass('jqtree-title') || $el.parent().hasClass('jqtree-title')) {
                    let node = e.node;

                    if (node.id === 'show-more') {
                        const previous = node.getPreviousSibling(),
                            parent = node.parent && node.parent.id ? node.parent : null;

                        this.getTreeEl().tree('removeNode', node);
                        return this.loadMore(parent, previous);
                    }

                    this.offsets = {root: 0};

                    let route = [];
                    while (node.parent.id) {
                        route.push(node.parent.id);
                        node = node.parent;
                    }

                    let data = {id: e.node.id, route: ''};
                    if (route.length > 0) {
                        data['route'] = "|" + route.reverse().join('|') + "|";
                    }

                    this.selectNode(data);
                }
            });
        },

        selectNode(data) {
            this.trigger('select-node', data);
        },

        getTreeEl() {
            return this.$el.find('.category-tree');
        },

        buildSearch() {
            this.createView('categorySearch', 'views/record/tree-panel/category-search', {
                el: '.catalog-tree-panel > .category-panel > .category-search',
                scope: this.treeScope,
                treePanel: this
            }, view => {
                view.render();
                this.listenTo(view, 'find-in-tree-panel', value => {
                    if (value && value !== '') {
                        this.getStorage().set('treeSearchValue', this.treeScope, value);
                    } else {
                        this.getStorage().clear('treeSearchValue', this.treeScope);
                    }
                    this.buildTree();
                    this.toggleVisibilityForResetButton();
                });
            });

            const treeScopes = this.getMetadata().get(`clientDefs.${this.scope}.treeScopes`);
            if (treeScopes) {
                this.getModelFactory().create(this.scope, model => {
                    model.set('scopesEnum', this.getStorage().get('treeScope', this.scope) || treeScopes[0]);

                    let options = [];

                    treeScopes.forEach(scope => {
                        if (this.getAcl().check(scope, 'read')) {
                            options.push(scope);
                        }
                    })

                    let translatedOptions = {};
                    options.forEach(scope => {
                        translatedOptions[scope] = this.translate(scope, 'scopeNames', 'Global');
                    });

                    this.createView('scopesEnum', 'views/fields/enum', {
                        prohibitedEmptyValue: true,
                        model: model,
                        el: `.catalog-tree-panel > .category-panel > .scopes-enum`,
                        defs: {
                            name: 'scopesEnum',
                            params: {
                                options: options,
                                translatedOptions: translatedOptions
                            }
                        },
                        mode: 'edit'
                    }, view => {
                        view.render();
                        this.listenTo(model, 'change:scopesEnum', () => {
                            this.treeScope = model.get('scopesEnum');

                            this.getStorage().set('treeScope', this.scope, this.treeScope);

                            this.getStorage().clear('selectedNodeId', this.scope);
                            this.getStorage().clear('selectedNodeRoute', this.scope);

                            const searchPanel = this.getView('categorySearch');
                            searchPanel.scope = this.treeScope;
                            searchPanel.reRender();

                            this.buildTree();

                            $('button[data-action="search"]').click();
                        });
                    });
                });
            }
        },

        actionCollapsePanel(type) {
            const $categoryPanel = this.$el.find('.category-panel');

            let isCollapsed = $categoryPanel.hasClass('hidden');
            if (type === 'open') {
                isCollapsed = true;
            }

            const $list = $('#tree-list-table');

            if (isCollapsed) {
                $categoryPanel.removeClass('hidden');
                $('.page-header').addClass('collapsed').removeClass('not-collapsed');
                if ($list.length > 0) {
                    $list.addClass('collapsed');
                } else {
                    $('.detail-button-container').addClass('collapsed').removeClass('not-collapsed');
                    $('.overview').addClass('collapsed').removeClass('not-collapsed');
                }
                this.showUtilityElements();
            } else {
                $categoryPanel.addClass('hidden');
                $('.page-header').removeClass('collapsed').addClass('not-collapsed');
                if ($list.length > 0) {
                    $list.removeClass('collapsed');
                } else {
                    $('.detail-button-container').removeClass('collapsed').addClass('not-collapsed');
                    $('.overview').removeClass('collapsed').addClass('not-collapsed');
                }
                this.hideUtilityElements();
            }

            if (!type) {
                this.getStorage().set('catalog-tree-panel', this.scope, isCollapsed ? '' : 'collapsed');
            }

            this.treePanelResize();
            $(window).trigger('resize');
        },

        showUtilityElements() {
            let button = this.$el.find('button[data-action="collapsePanel"]');
            button.removeClass('collapsed');
            button.find('span.toggle-icon-left').removeClass('hidden');
            button.find('span.toggle-icon-right').addClass('hidden');

            this.$el.removeClass('catalog-tree-panel-hidden');

            this.$el.addClass('col-xs-12 col-lg-3');
            if (this.model) {

                let detailContainer = this.$el.parents('#main').find('.overview');
                detailContainer.removeClass('col-lg-9');
                detailContainer.addClass('col-lg-6');

            } else {
                let listContainer = this.$el.parents('#main').find('.list-container');
                listContainer.addClass('col-xs-12 col-lg-9');
            }
        },

        hideUtilityElements() {
            let button = this.$el.find('button[data-action="collapsePanel"]');
            button.addClass('collapsed');
            button.find('span.toggle-icon-left').addClass('hidden');
            button.find('span.toggle-icon-right').removeClass('hidden');

            this.$el.addClass('catalog-tree-panel-hidden');

            this.$el.removeClass('col-xs-12 col-lg-3');
            if (this.model) {

                let detailContainer = this.$el.parents('#main').find('.overview');
                detailContainer.addClass('col-lg-9');
                detailContainer.removeClass('col-lg-6');

            } else {
                let listContainer = this.$el.parents('#main').find('.list-container');
                listContainer.removeClass('col-xs-12 col-lg-9');
            }
        },

    })
);
