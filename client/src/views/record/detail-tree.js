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

Espo.define('views/record/detail-tree', 'views/record/detail',
    Dep => Dep.extend({

        template: 'record/detail-tree',

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.isWide && this.type !== 'editSmall' && this.type !== 'detailSmall') {
                this.isTreePanel = true;
                this.setupTreePanel();
            }
        },

        data() {
            return _.extend({isTreePanel: this.isTreePanel}, Dep.prototype.data.call(this))
        },

        setupTreePanel() {
            this.createView('treePanel', 'views/record/panels/tree-panel', {
                el: `${this.options.el} .catalog-tree-panel`,
                scope: this.scope,
                model: this.model
            }, view => {
                this.listenTo(this.model, 'after:save', () => {
                    view.reRender();
                });
                view.listenTo(view, 'select-node', data => {
                    this.selectNode(data);
                });
                view.listenTo(view, 'tree-init', () => {
                    this.treeInit(view);
                });
                this.listenTo(this.model, 'after:relate after:unrelate after:dragDrop', link => {
                    if (['parents', 'children'].includes(link)) {
                        view.reRender();
                    }
                });
                this.listenTo(view, 'tree-width-changed', function (width) {
                    const content = $('#content');
                    const main = content.find('#main');

                    const header = content.find('.page-header');
                    const btnContainer = content.find('.detail-button-container');
                    const overview = content.find('.overview');
                    const side = content.find('.side');

                    header.outerWidth(main.width() - width - 9);
                    header.css('marginLeft', width + 'px');

                    btnContainer.outerWidth(main.width() - width - 9);
                    btnContainer.css('marginLeft', width + 'px');

                    overview.outerWidth(content.innerWidth() - side.outerWidth() - width - 9);
                    overview.css('marginLeft', (width - 1) + 'px');
                });
                this.listenTo(view, 'tree-width-unset', function () {
                    $('.page-header').css({'width': 'unset', 'marginLeft': 'unset'});
                    $('.detail-button-container').css({'width': 'unset', 'marginLeft': 'unset'});
                    $('.overview').css({'width': 'unset', 'marginLeft': 'unset'});
                })
            });
        },

        selectNode(data) {
            window.location.href = `/#${this.scope}/view/${data.id}`;
        },

        getCurrentTime() {
            return Math.floor(new Date().getTime() / 1000);
        },

        treeInit(view) {
            if (view.model && view.model.get('id')) {
                this.ajaxGetRequest(`${this.scope}/action/route?id=${view.model.get('id')}`).then(route => {
                    view.selectTreeNode(route, view.model.get('id'));
                });
            }
        },

    })
);

