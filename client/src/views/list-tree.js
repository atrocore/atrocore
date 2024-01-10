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

Espo.define('views/list-tree', 'views/list', function (Dep) {

    return Dep.extend({

        template: 'list-tree',

        setup() {
            Dep.prototype.setup.call(this);

            this.setupTreePanel();
        },

        afterRender() {
            let treePanelView = this.getView('treePanel');

            this.collection.isFetched = false;
            this.clearView('list');

            if (treePanelView && this.getStorage().get('reSetupSearchManager', treePanelView.treeScope)) {
                this.getStorage().clear('reSetupSearchManager', treePanelView.treeScope);
                this.setupSearchManager();
            }

            Dep.prototype.afterRender.call(this);

            let observer = new ResizeObserver(() => {
                if (treePanelView) {
                    this.onTreeResize(treePanelView.$el.outerWidth());
                }
            });
            observer.observe($('#content').get(0));
        },

        isTreeAllowed() {
            let result = false;

            let treeScopes = this.getMetadata().get(`clientDefs.${this.scope}.treeScopes`) || [this.scope];

            treeScopes.forEach(scope => {
                if (this.getAcl().check(scope, 'read')) {
                    result = true;
                    if (!this.getStorage().get('treeScope', this.scope)) {
                        this.getStorage().set('treeScope', this.scope, scope);
                    }
                }
            });

            return result;
        },

        setupTreePanel(scope) {
            if (!this.isTreeAllowed() || this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`)) {
                return;
            }

            this.createView('treePanel', 'views/record/panels/tree-panel', {
                el: `${this.options.el} .catalog-tree-panel`,
                scope: scope ? scope : this.scope,
                model: this.model,
                collection: this.collection
            }, view => {
                view.listenTo(view, 'select-node', data => {
                    this.selectNode(data);
                });
                view.listenTo(view, 'tree-load', () => {
                    this.treeLoad(view);
                });
                view.listenTo(view, 'tree-reset', () => {
                    this.treeReset(view);
                });
                this.listenTo(view, 'tree-width-changed', function (width) {
                    this.onTreeResize(width)
                });
                this.listenTo(view, 'tree-width-unset', function () {
                    if ($('.catalog-tree-panel').length) {
                        $('.page-header').css({'width': 'unset', 'marginLeft': 'unset'});
                        $('.advanced-filters').css({'width': 'unset'});
                        $('#tree-list-table.list-container').css({'width': 'unset', 'marginLeft': 'unset'});
                    }
                })
            });
        },

        resetSorting() {
            Dep.prototype.resetSorting.call(this);

            this.getStorage().clear('selectedNodeId', this.scope);
            this.getStorage().clear('selectedNodeRoute', this.scope);

            let treeView = this.getView('treePanel');
            if (treeView) {
                treeView.rebuildTree();
            }
        },

        treeLoad(view) {
            if (this.getStorage().get('selectedNodeId', this.scope)) {
                this.selectTreeNode();
            }
        },

        selectTreeNode() {
            const id = this.getStorage().get('selectedNodeId', this.scope);
            const route = this.parseRoute(this.getStorage().get('selectedNodeRoute', this.scope));

            this.getView('treePanel').selectTreeNode(id, route);

            const filterName = "linkedWith" + this.getStorage().get('treeScope', this.scope);

            this.notify('Please wait...');

            let data = {bool: {}, boolData: {}};
            data['bool'][filterName] = true;
            data['boolData'][filterName] = id;

            const defaultFilters = Espo.Utils.cloneDeep(this.searchManager.get());
            const extendedFilters = Espo.Utils.cloneDeep(defaultFilters);

            $.each(data, (key, value) => {
                extendedFilters[key] = _.extend({}, extendedFilters[key], value);
            });

            this.searchManager.set(extendedFilters);
            this.collection.where = this.searchManager.getWhere();
            this.searchManager.set(defaultFilters);

            this.collection.fetch().then(() => this.notify(false));
        },

        unSelectTreeNode(id) {
            this.getView('treePanel').unSelectTreeNode(id);

            this.notify('Please wait...');

            const defaultFilters = Espo.Utils.cloneDeep(this.searchManager.get());
            const extendedFilters = Espo.Utils.cloneDeep(defaultFilters);
            $.each({bool: {}, boolData: {}}, (key, value) => {
                extendedFilters[key] = _.extend({}, extendedFilters[key], value);
            });
            this.searchManager.set(extendedFilters);
            this.collection.where = this.searchManager.getWhere();
            this.searchManager.set(defaultFilters);

            this.collection.fetch().then(() => this.notify(false));
        },

        treeReset(view) {
            this.notify('Please wait...');

            this.getStorage().clear('selectedNodeId', this.scope);
            this.getStorage().clear('selectedNodeRoute', this.scope);

            this.getStorage().clear('treeSearchValue', view.treeScope);
            view.toggleVisibilityForResetButton();

            this.getView('search').silentResetFilters();
        },

        selectNode(data) {
            if (this.getStorage().get('treeScope', this.scope) === this.scope) {
                window.location.href = `/#${this.scope}/view/${data.id}`;
                return;
            }

            if (data.id === this.getStorage().get('selectedNodeId', this.scope)) {
                this.getStorage().clear('selectedNodeId', this.scope);
                this.getStorage().clear('selectedNodeRoute', this.scope);
                this.unSelectTreeNode(data.id);

                return;
            }

            this.getStorage().set('selectedNodeId', this.scope, data.id);
            this.getStorage().set('selectedNodeRoute', this.scope, data.route);

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
            const content = $('#content');
            const listContainer = content.find('#main > #tree-list-table.list-container');

            if ($('.catalog-tree-panel').length && listContainer.length) {
                const main = content.find('#main');

                const header = content.find('.page-header');
                const filters = content.find('.advanced-filters');

                header.outerWidth(main.width() - width);
                header.css('marginLeft', width + 'px');

                filters.outerWidth(main.width() - width);

                listContainer.outerWidth(main.width() - width);
                listContainer.css('marginLeft', (width - 1) + 'px');
            }
        }
    });
});

