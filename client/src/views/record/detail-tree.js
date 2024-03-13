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

Espo.define('views/record/detail-tree', 'views/record/detail',
    Dep => Dep.extend({

        template: 'record/detail-tree',

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.isWide && this.type !== 'editSmall' && this.type !== 'detailSmall') {
                this.isTreePanel = this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`) !== true;
                this.setupTreePanel();
            }
        },

        data() {
            return _.extend({isTreePanel: this.isTreePanel}, Dep.prototype.data.call(this))
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const treePanel = this.getView('treePanel');

            let observer = new ResizeObserver(() => {
                if (treePanel && treePanel.$el) {
                    this.onTreeResize();
                }

                observer.unobserve($('#content').get(0));
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
            })

            return result;
        },

        setupTreePanel() {
            if (!this.isTreeAllowed()  || this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`)) {
                return;
            }

            this.createView('treePanel', 'views/record/panels/tree-panel', {
                el: `${this.options.el} .catalog-tree-panel`,
                scope: this.scope,
                model: this.model
            }, view => {
                this.listenTo(this.model, 'after:save', () => {
                    view.rebuildTree();
                });
                view.listenTo(view, 'select-node', data => {
                    this.selectNode(data);
                });
                view.listenTo(view, 'tree-load', treeData => {
                    this.treeLoad(view, treeData);
                });
                view.listenTo(view, 'tree-reset', () => {
                    this.treeReset(view);
                });
                this.listenTo(this.model, 'after:relate after:unrelate after:dragDrop', link => {
                    if (['parents', 'children'].includes(link)) {
                        view.rebuildTree();
                    }
                });
                this.listenTo(view, 'tree-width-changed', function (width) {
                    this.onTreeResize(width)
                });
                this.listenTo(view, 'tree-width-unset', function () {
                    if ($('.catalog-tree-panel').length) {
                        $('.page-header').css({'width': 'unset', 'marginLeft': 'unset'});
                        $('.overview-filters-container').css({'width': 'unset', 'marginLeft': 'unset'})
                        $('.detail-button-container').css({'width': 'unset', 'marginLeft': 'unset'});
                        $('.overview').css({'width': 'unset', 'marginLeft': 'unset'});
                    }
                })
            });
        },

        selectNode(data) {
            if (this.getStorage().get('treeScope', this.scope) === this.scope) {
                window.location.href = `/#${this.scope}/view/${data.id}`;
            } else {
                this.getStorage().set('selectedNodeId', this.scope, data.id);
                this.getStorage().set('selectedNodeRoute', this.scope, data.route);
                window.location.href = `/#${this.scope}`;
            }
        },

        treeLoad(view, treeData) {
            if (view.model && view.model.get('id')) {
                let route = [];
                view.prepareTreeRoute(treeData, route);
                view.selectTreeNode(view.model.get('id'), route);
            }
        },

        treeReset(view) {
            this.getStorage().clear('selectedNodeId', this.scope);
            this.getStorage().clear('selectedNodeRoute', this.scope);

            this.getStorage().clear('treeSearchValue', view.treeScope);
            this.getStorage().clear('treeWhereData', view.treeScope);

            this.getStorage().clear('listSearch', view.treeScope);
            this.getStorage().set('reSetupSearchManager', view.treeScope, true);

            view.toggleVisibilityForResetButton();
            view.rebuildTree();
        },

        onTreeResize(width) {
            if ($('.catalog-tree-panel').length) {
                width = parseInt(width || $('.catalog-tree-panel').outerWidth());

                const content = $('#content');
                const main = content.find('#main');

                const header = content.find('.page-header');
                const btnContainer = content.find('.detail-button-container');
                const filters = content.find('.overview-filters-container');
                const overview = content.find('.overview');
                const side = content.find('.side');

                header.outerWidth(Math.floor(main.width() - width));
                header.css('marginLeft', width + 'px');

                filters.outerWidth(Math.floor(content.get(0).getBoundingClientRect().width - width));
                filters.css('marginLeft', width + 'px');

                btnContainer.outerWidth(Math.floor(content.get(0).getBoundingClientRect().width - width - 1));
                btnContainer.addClass('detail-tree-button-container');
                btnContainer.css('marginLeft', width + 1 + 'px');

                overview.outerWidth(Math.floor(content.innerWidth() - side.outerWidth() - width));
                overview.css('marginLeft', width + 'px');
            }
        }
    })
);

