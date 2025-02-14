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
            'relationships',
            'sidePanelsDetail'
        ],

        scope: null,

        type: null,

        relatedScope: null,

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
                if (this.scope) {
                    this.openLayout(this.scope, this.type, this.relatedScope, this.layoutProfileId);
                }
                this.listenTo(this.model, 'change:entity change:viewType change:layoutProfileId change:relatedEntity', () => {
                    if (this.model.get('entity') && this.model.get('viewType') && this.model.get('layoutProfileId')) {
                        const viewType = this.getView("viewType")
                        if (viewType && !viewType.getAvailableOptions().includes(this.model.get('viewType'))) {
                            return;
                        }
                        const relatedEntity = this.getView("relatedEntity")
                        if (relatedEntity && this.model.get('relatedEntity') && !relatedEntity.getAvailableOptions().includes(this.model.get('relatedEntity'))) {
                            return;
                        }
                        this.openLayout(this.model.get('entity'), this.model.get('viewType'), this.model.get('relatedEntity') ?? null, this.model.get('layoutProfileId'))
                    }
                })
            });

            this.scope = this.options.scope || 'Account';
            this.type = this.options.type || 'list';
            this.layoutProfileId = this.options.layoutProfileId;
            this.relatedScope = this.options.relatedScope ?? null;

            this.getModelFactory().create('Layout', (model) => {
                this.model = model;
                model.set('id', '1')
                model.set('entity', this.scope)
                model.set('viewType', this.type)
                model.set('relatedEntity', this.relatedScope)
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
                    inlineEditDisabled: true,
                    prohibitedEmptyValue: true
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
                    inlineEditDisabled: true,
                    prohibitedEmptyValue: true
                });

                this.createView('relatedEntity', 'views/layout/fields/related-entity', {
                    name: 'relatedEntity',
                    el: `${this.options.el} .field[data-name="relatedEntity"]`,
                    model: this.model,
                    scope: 'Layout',
                    defs: {
                        name: 'relatedEntity',
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
                    inlineEditDisabled: true,
                    prohibitedEmptyValue: true
                })
            })
        },

        openLayout: function (scope, type, relatedScope, layoutProfileId) {
            this.scope = scope;
            this.type = type;
            this.relatedScope = relatedScope
            this.layoutProfileId = layoutProfileId

            this.getRouter().navigate('#Admin/layouts/scope=' + scope + '&type=' + type
                + (relatedScope ? ('&relatedScope=' + relatedScope) : '')
                + (layoutProfileId ? ('&layoutProfileId=' + layoutProfileId) : ''), {trigger: false});

            LayoutUtils.renderComponent.call(this, {
                type: type,
                scope: scope,
                relatedScope: relatedScope,
                layoutProfileId: layoutProfileId,
                editable: true,
                layoutProfiles: this.getView('layoutProfile').params.linkOptions,
                replaceButtons: true
            })
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Layout Manager', 'labels', 'Admin'));
        },
    });
});


