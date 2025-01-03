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

                if (this.getStorage().get('catalog-tree-panel', this.scope) !== 'collapsed') {
                    this.notify('Loading...')
                    this.rebuildTree()
                }
            },

            'click .reset-tree-filter': function (e) {
                e.preventDefault();
                this.trigger('tree-reset');
            }
        },

        setup() {
            this.scope = this.options.scope || this.scope;
            this.treeScope = this.scope;

            let treeScopes = this.getTreeScopes();
            if (treeScopes) {
                const treeScope = this.getStorage().get('treeScope', this.scope);
                if (!treeScope || !treeScopes.includes(treeScope)) {
                    this.getStorage().set('treeScope', this.scope, treeScopes[0]);
                }else{
                    this.treeScope = treeScope;
                }
            }

            this.wait(true);
            this.buildSearch();
            this.wait(false);

            this.currentWidth = this.getStorage().get('panelWidth', this.scope) || this.minWidth;

            this.maxSize = this.getConfig().get('recordsPerPageSmall', 20);

            if (this.options.collection) {
                this.listenTo(Backbone, 'after:search', collection => {
                    if (this.options.collection.name === collection.name) {

                        if (collection.name === this.treeScope) {
                            this.getStorage().set('treeWhereData', this.treeScope, collection.where);
                        }

                        if (this.getStorage().get('catalog-tree-panel', this.scope) !== 'collapsed') {
                            this.rebuildTree()
                        }
                    }
                });
            }

            this.listenTo(this, 'tree-load', function (data) {
                this.notify(false)
            })
        },

        data() {
            return {
                scope: this.scope,
                treeScope: this.treeScope
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if ($(window).width() <= 767 || !!this.getStorage().get('catalog-tree-panel', this.scope)) {
                this.actionCollapsePanel();
            } else {
                this.actionCollapsePanel('open');
            }

            $(window).on('resize load', () => {
                this.treePanelResize()
            });

            this.toggleVisibilityForResetButton();

            if (this.getStorage().get('catalog-tree-panel', this.scope) !== 'collapsed') {
                this.buildTree()
            }
        },

        toggleVisibilityForResetButton() {
            let $reset = this.$el.find('.reset-search-in-tree-button');
            if (this.getStorage().get('treeSearchValue', this.treeScope)) {
                $reset.show();
            } else {
                this.$el.find('.search-in-tree-input').val('');
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

        openNodes($tree, ids, onFinished) {
            if (ids.length === 0) {
                onFinished()
                return
            }

            const item = ids[0]
            let $els = $tree.find(`.jqtree-title[data-id="${item}"]`);
            if ($els.length > 0) {
                $els.each((k, el) => {
                    let $el = $(el);
                    let $li = $el.parent().parent();
                    if ($li.hasClass('jqtree-closed')) {
                        result = true;
                        let node = $tree.tree('getNodeByHtmlElement', $el);
                        $tree.tree('openNode', node, false, () => this.openNodes($tree, ids.slice(1), onFinished));
                    }
                });
            }
        },

        clearStorage() {
            this.getStorage().clear('selectedNodeId', this.scope);
            this.getStorage().clear('selectedNodeRoute', this.scope);
        },

        prepareTreeRoute(list, route) {
            list.forEach(item => {
                if (item.children) {
                    route.push(item.id);
                    this.prepareTreeRoute(item.children, route);
                }
            });

            if (this.scope && this.model.get('id')) {
                this.getStorage().set('selectedNodeId', this.scope, this.model.get('id'));
                this.getStorage().set('selectedNodeRoute', this.scope, route);
            }
        },

        treeRefresh() {
            if (this.getStorage().get('selectedNodeId', this.scope)) {
                const id = this.getStorage().get('selectedNodeId', this.scope);
                const route = this.getStorage().get('selectedNodeRoute', this.scope);
                this.selectTreeNode(id, route);
            }
        },

        selectTreeNode(id, ids) {
            const $tree = this.getTreeEl();
            const onFinished = () => {
                let node = $tree.tree('getNodeById', id);
                if (node) {
                    $tree.tree('addToSelection', node);
                }

                $tree.find(`.jqtree-title`).each((k, el) => {
                    let $el = $(el);
                    let $li = $el.parent().parent();

                    if ($el.data('id') !== id && $tree.tree('getNodeById', $el.data('id'))) {
                        $tree.tree('removeFromSelection', $tree.tree('getNodeById', $el.data('id')));
                        $li.removeClass('jqtree-selected');
                    } else if (!$li.hasClass('jqtree-selected')) {
                        $li.addClass('jqtree-selected');
                    }
                });
            }

            this.openNodes($tree, ids, onFinished)

        },

        unSelectTreeNode(id) {
            const $tree = this.getTreeEl();
            const node = $tree.tree('getNodeById', id);

            if (node) {
                $tree.tree('removeFromSelection', node);
            }
        },

        generateUrl(node) {
            let url = this.treeScope + `/action/Tree?isTreePanel=1&scope=${this.scope}`;
            if (node && node.showMoreDirection) {
                let offset = node.offset;
                let maxSize = this.maxSize;
                if (node.showMoreDirection === 'up') {
                    let diff = node.offset - this.maxSize;
                    offset = node.offset - this.maxSize;
                    if (diff < 0) {
                        offset = 0;
                        maxSize = maxSize + diff;
                    } else {
                        offset = diff;
                    }
                } else if (node.showMoreDirection === 'down') {
                    offset = offset + 1;
                }
                url += '&offset=' + offset + '&maxSize=' + maxSize;
                if (node.getParent()) {
                    url += '&node=' + node.getParent().id;
                }
            } else if (node && node.id) {
                url += '&node=' + node.id + '&offset=0&maxSize=' + this.maxSize;
            } else if (this.model && this.model.id && [this.model.urlRoot, 'Bookmark'].includes(this.treeScope)) {
                url += '&selectedId=' + this.model.id;
            }

            let whereData = this.getStorage().get('treeWhereData', this.treeScope) || [];
            if (whereData.length > 0) {
                url += "&";
                url += $.param({"where": whereData});
            }
            return url;
        },

        loadMore(node) {
            this.ajaxGetRequest(this.generateUrl(node)).then(response => {
                if (response['list']) {
                    const $tree = this.getTreeEl();
                    if (node.showMoreDirection === 'up') {
                        // prepend
                        this.filterResponse(Espo.Utils.cloneDeep(response), 'up').reverse().forEach(item => {
                            this.prependNode($tree, item, node.getParent());
                        });
                    } else if (node.showMoreDirection === 'down') {
                        // append
                        this.filterResponse(Espo.Utils.cloneDeep(response), 'down').forEach(item => {
                            this.appendNode($tree, item, node.getParent());
                        });
                    }
                    $tree.tree('removeNode', node);
                }
            });
        },

        appendNode($tree, item, parent) {
            let element = parent || $tree.tree('getTree'),
                nodes = (element.children || []);

            if (nodes.findIndex(node => item.id === node.id) === -1) {
                $tree.tree('appendNode', item, parent);
            }
        },

        prependNode($tree, item, parent) {
            let element = parent || $tree.tree('getTree'),
                nodes = (element.children || []);

            if (nodes.findIndex(node => item.id === node.id) === -1) {
                $tree.tree('prependNode', item, parent);
            }
        },

        pushShowMore(list, direction) {
            if (!direction || direction === 'up') {
                let first = Espo.Utils.cloneDeep(list).shift();
                if (first && first.offset && first.offset !== 0) {
                    list.unshift({
                        id: 'show-more-' + first.offset,
                        offset: first.offset,
                        showMoreDirection: 'up',
                        name: this.getLanguage().translate('Show more')
                    });
                }
            }

            if (!direction || direction === 'down') {
                let last = Espo.Utils.cloneDeep(list).pop();
                if (last && last.offset && last.total - 1 !== last.offset) {
                    list.push({
                        id: 'show-more-' + last.offset,
                        offset: last.offset,
                        showMoreDirection: 'down',
                        name: this.getLanguage().translate('Show more')
                    });
                }
            }

            list.forEach(item => {
                if (item.children) {
                    this.pushShowMore(item.children);
                }
            });
        },

        filterResponse(response, direction = null) {
            if (!response.list) {
                return response;
            }

            this.pushShowMore(response.list, direction);

            return response.list;
        },

        isRootNode(node) {
            return !node.parent.getLevel();
        },

        destroyTree() {
            let tree = this.getTreeEl();
            if (tree) {
                tree.tree('destroy');
            }
        },

        rebuildTree() {
            this.destroyTree();
            this.buildTree();
        },

        buildTree(data = null) {
            let $tree = this.getTreeEl();
            let whereData = this.getStorage().get('treeWhereData', this.treeScope) || [];
            let searchValue = this.getStorage().get('treeSearchValue', this.treeScope) || null;

            if (data === null && searchValue) {
                this.$el.find('.search-in-tree-input').val(searchValue);
                whereData.push({"type": "textFilter", "value": searchValue})
                $tree.html(this.translate('Loading...'));
                this.ajaxGetRequest(`${this.treeScope}/action/TreeData`, {"where": whereData, "scope": this.scope}).then(response => {
                    this.buildTree(response.tree);
                });
                return;
            }

            let treeData = {
                dataUrl: this.generateUrl(),
                dataFilter: function (response) {
                    return this.filterResponse(response);
                }.bind(this),
                selectable: true,
                saveState: false,
                autoOpen: false,
                dragAndDrop: this.getMetadata().get(`scopes.${this.treeScope}.multiParents`) !== true && this.getMetadata().get(`scopes.${this.treeScope}.dragAndDrop`),
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

                    let treeScope = this.getStorage().get('treeScope', this.scope);

                    if (data && [this.scope, 'Bookmark'].includes(treeScope) && this.model && this.model.get('id') === node.id) {
                        $tree.tree('addToSelection', node);
                        $li.addClass('jqtree-selected');
                    }

                    $title.attr('data-id', node.id);

                    if (treeData.dragAndDrop && !node.showMoreDirection) {
                        $title.attr('title', this.translate("useDragAndDrop"));
                    }

                    if (node.showMoreDirection) {
                        $li.addClass('show-more');
                        $li.addClass('show-more-' + node.showMoreDirection);
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

            $tree.tree(treeData)
                .on('tree.load_data', e => {
                    this.trigger('tree-load', e.tree_data)
                })
                .on('tree.refresh', e => {
                    this.trigger('tree-refresh', e)
                })
                .on('tree.move', e => {
                    e.preventDefault();

                    const parentName = 'parent';

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
                    });
                }).on('tree.click', e => {
                e.preventDefault();
                if (e.node.disabled) {
                    return false;
                }

                const $el = $(e.click_event.target);
                if ($el.hasClass('jqtree-title') || $el.parent().hasClass('jqtree-title')) {
                    let node = e.node;

                    if (node.showMoreDirection) {
                        return this.loadMore(node);
                    }

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
                    this.rebuildTree();
                    this.toggleVisibilityForResetButton();
                });
            });

            const treeScopes = this.getTreeScopes();

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

                            this.rebuildTree();
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

        getTreeScopes() {
            let treeScopes = this.getMetadata().get(`clientDefs.${this.scope}.treeScopes`) || [];
            if(!treeScopes.includes(this.scope)
                && this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy'
                && !this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`)
            ) {
                treeScopes.unshift(this.scope);
            }
            return treeScopes;
        }

    })
);
