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

Espo.define('views/list-tree', 'views/list', function (Dep) {

    return Dep.extend({

        template: 'list-tree',

        setup() {
            Dep.prototype.setup.call(this);

            this.setupTreePanel();
        },

        afterRender() {
            this.collection.isFetched = false;
            this.clearView('list');

            Dep.prototype.afterRender.call(this);

            let observer = new ResizeObserver(() => {
                let view = this.getView('treePanel');

                if (view) {
                    let width = view.$el.outerWidth();

                    this.onTreeResize(width);
                }
            });
            observer.observe($('#content').get(0));
        },

        setupTreePanel() {
            this.createView('treePanel', 'views/record/panels/tree-panel', {
                el: `${this.options.el} .catalog-tree-panel`,
                scope: this.scope,
                model: this.model
            }, view => {
                view.listenTo(view, 'select-node', data => {
                    this.selectNode(data);
                });
                view.listenTo(view, 'tree-init', () => {
                    this.treeInit(view);
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

        treeInit(view) {
        },

        treeReset(view) {
            window.location.href = `/#${this.scope}`;
        },

        selectNode(data) {
            window.location.href = `/#${this.scope}/view/${data.id}`;
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

