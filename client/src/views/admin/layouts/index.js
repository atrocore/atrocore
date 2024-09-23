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

Espo.define('views/admin/layouts/index', ['view', 'views/admin/layouts/layout-utils'], function (Dep, LayoutUtils) {

    return Dep.extend({

        template: 'admin/layouts/index',

        typeList: [
            'list',
            'detail',
            'listSmall',
            'detailSmall',
            'relationships',
            'sidePanelsDetail',
            'sidePanelsEdit',
            'sidePanelsDetailSmall',
            'sidePanelsEditSmall'
        ],

        scope: null,

        type: null,

        data: function () {
            return {
                typeList: this.typeList,
                scope: this.scope
            };
        },

        setup: function () {
            this.scopeList = [];

            this.on('after:render', function () {
                $("#layouts-menu button[data-scope='" + this.options.scope + "'][data-type='" + this.options.type + "']").addClass('disabled');
                this.renderLayoutHeader();
                if (!this.options.scope) {
                    this.renderDefaultPage();
                }
                if (this.scope) {
                    this.openLayout(this.scope, this.type, this.layoutProfileId);
                }
                this.listenTo(this.model, 'change:entity change:viewType change:layoutProfileId', () => {
                    console.log('update')
                    if (this.model.get('entity') && this.model.get('viewType') && this.model.get('layoutProfileId')) {
                        if (this.model.get('viewType') === 'kanban' && !this.getMetadata().get(['clientDefs', this.model.get('entity'), 'kanbanViewMode'])) {
                            return
                        }
                        this.openLayout(this.model.get('entity'), this.model.get('viewType'), this.model.get('layoutProfileId'))
                    }
                })
            });

            this.scope = this.options.scope || 'Account';
            this.type = this.options.type || 'list';
            this.layoutProfileId = this.options.layoutProfileId || 'custom';

            this.getModelFactory().create('Layout', (model) => {
                this.model = model;
                model.set('id', '1')
                model.set('entity', this.scope)
                model.set('viewType', this.type)
                model.set('layoutProfileId', this.layoutProfileId)

                // create field views
                this.createView('entity', 'views/layout/fields/entity', {
                    name: 'entity',
                    el: `${this.options.el} .field[data-name="entity"]`,
                    model: this.model,
                    scope: 'Layout',
                    defs: {
                        name: 'entity',
                    },
                    mode: 'edit',
                    inlineEditDisabled: true
                });

                this.createView('viewType', 'views/layout/fields/view-type', {
                    name: 'viewType',
                    el: `${this.options.el} .field[data-name="viewType"]`,
                    model: this.model,
                    scope: 'Layout',
                    defs: {
                        name: 'viewType'
                    },
                    mode: 'edit',
                    inlineEditDisabled: true
                });

                this.createView('layoutProfile', 'views/layout/fields/layout-profile-dropdown', {
                    name: 'layoutProfile',
                    el: `${this.options.el} .field[data-name="layoutProfile"]`,
                    model: this.model,
                    scope: 'Layout',
                    defs: {
                        name: 'layoutProfile',
                    },
                    mode: 'edit',
                    inlineEditDisabled: true
                })
            })
        },

        openLayout: function (scope, type, layoutProfileId) {
            this.scope = scope;
            this.type = type;
            this.layoutProfileId = layoutProfileId

            this.getRouter().navigate('#Admin/layouts/scope=' + scope + '&type=' + type + (layoutProfileId ? ('&layoutProfileId=' + layoutProfileId) : ''), {trigger: false});


            var typeReal = this.getMetadata().get('clientDefs.' + scope + '.additionalLayouts.' + type + '.type') || type;

            LayoutUtils.renderComponent.call(this, {
                type: type,
                scope: scope,
                layoutProfileId: layoutProfileId,
                editable: true,
                afterRender: () => {
                    this.renderLayoutHeader();
                },
            })
        },

        renderDefaultPage: function () {
            $("#layout-header").html('').hide();
            $("#layout-content").html(this.translate('selectLayout', 'messages', 'Admin'));
        },

        renderLayoutHeader: function () {
            if (!this.scope) {
                $("#layout-header").html("");
                return;
            }
            $("#layout-header").show().html(this.getLanguage().translate(this.scope, 'scopeNamesPlural') + " » " + this.getLanguage().translate(this.type, 'layouts', 'Admin'));
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Layout Manager', 'labels', 'Admin'));
        },
    });
});


