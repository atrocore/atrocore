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

Espo.define('views/record/panels/tree-panel', ['view'],
    Dep => Dep.extend({

        template: 'record/tree-panel',

        minWidth: 220,

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




        clearStorage() {
            this.getStorage().clear('selectedNodeId', this.scope);
            this.getStorage().clear('selectedNodeRoute', this.scope);
        },



        treeRefresh() {
            if (this.getStorage().get('selectedNodeId', this.scope)) {
                const id = this.getStorage().get('selectedNodeId', this.scope);
                const route = this.getStorage().get('selectedNodeRoute', this.scope);
                this.selectTreeNode(id, route);
            }
        },



        unSelectTreeNode(id) {
            const $tree = this.getTreeEl();
            const node = $tree.tree('getNodeById', id);

            if (node) {
                $tree.tree('removeFromSelection', node);
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



        isRootNode(node) {
            return !node.parent.getLevel();
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
                        translatedOptions[scope] = this.translate(scope, 'scopeNamesPlural', 'Global');
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
                }
                this.showUtilityElements();
            } else {
                $categoryPanel.addClass('hidden');
                $('.page-header').removeClass('collapsed').addClass('not-collapsed');
                if ($list.length > 0) {
                    $list.removeClass('collapsed');
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
        },

        hideUtilityElements() {
            let button = this.$el.find('button[data-action="collapsePanel"]');
            button.addClass('collapsed');
            button.find('span.toggle-icon-left').addClass('hidden');
            button.find('span.toggle-icon-right').removeClass('hidden');

            this.$el.addClass('catalog-tree-panel-hidden');

            this.$el.removeClass('col-xs-12 col-lg-3');
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
