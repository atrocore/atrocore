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

Espo.define('views/record/row-actions/default', 'view', function (Dep) {

    return Dep.extend({

        _template: '',

        hiddenActionTypes: ['previewTemplate'],

        setup: function () {
            this.options.acl = this.options.acl || {};
        },

        getQuickActions: function () {
            const configured = (this.getMetadata().get(['clientDefs', this.model.name, 'quickActions']) || []);
            return configured.map(name => this.adaptQuickAction(name));
        },

        adaptQuickAction: function (name) {
            return name;
        },

        afterRender: function () {
            this.svelteComponent?.$destroy();

            if (!this.$el[0]) return;

            const quickActions = this.getQuickActions();

            const actions = this.getActionList().map(a => ({
                name: a.action,
                label: a.label,
                iconClass: a.iconClass || undefined,
                iconUrl: a.iconUrl || undefined,
                link: a.link || undefined,
                data: a.data || undefined,
                quick: quickActions.includes(a.action),
            }));

            this.svelteComponent = new Svelte.RowActions({
                target: this.$el[0],
                props: { actions, itemId: this.model.id, loadActions: this.getLoadActions() }
            });

            this.svelteComponent.$on('dropdownShow', () => {
                this.$el.closest('.list-row').addClass('active-dropdown');
            });

            this.svelteComponent.$on('dropdownHide', () => {
                this.$el.closest('.list-row').removeClass('active-dropdown');
            });
        },

        onRemove: function () {
            this.svelteComponent?.$destroy();
        },

        getLoadActions: function () {
            const scope = this.model.name;
            if (this.getMetadata().get(['scopes', scope, 'actionDisabled'])) {
                return undefined;
            }

            const filters = this.getStorage().get('listQueryBuilder', scope);
            if (filters?.bool?.onlyDeleted === true) {
                return undefined;
            }

            return () => this.model.fetchDynamicActions('record').then(dynamicActions =>
                dynamicActions
                    .filter(a => !this.hiddenActionTypes.includes(a.type) && !this.getMetadata().get(['action', 'typesData', a.type, 'forEditModeOnly']))
                    .map(a => ({
                        name: a.action,
                        label: a.label,
                        link: a.link || undefined,
                        data: a.data || undefined,
                        iconUrl: a.iconUrl || undefined,
                    }))
            );
        },

        getActionList: function () {
            const scope = this.options.scope ?? this.model.name;
            const filters = this.getStorage().get('listQueryBuilder', scope);

            let actionsSortOrder = {
                "quickView": 110,
                "quickEdit": 120,
                "select": 130,
                "quickCompare": 140,
                "quickRemove": 150,
            }

            if(filters?.bool?.onlyDeleted === true) {
                actionsSortOrder = {
                    "quickRestore": 160,
                    "deletePermanently": 170
                };
            }

            $.each(this.getMetadata().get(['clientDefs', scope, 'listActions']) || {}, (actionName, actionData) => {
                if (actionData.sortOrder) {
                    actionsSortOrder[actionName] = actionData.sortOrder;
                }
                if (!actionsSortOrder[actionName]) {
                    actionsSortOrder[actionName] = 100;
                }
                if (actionData.disabled === true && actionsSortOrder[actionName]) {
                    delete actionsSortOrder[actionName];
                }
            });

            const sortedActionKeys = Object.keys(actionsSortOrder)
                .sort((a, b) => actionsSortOrder[a] - actionsSortOrder[b]);

            let list = [];

            sortedActionKeys.forEach(actionName => {
                if (actionName === 'quickRestore') {
                    if (filters && filters?.bool?.onlyDeleted === true) {
                        if (this.model.get('_meta')?.permissions?.delete) {
                            list.push({
                                action: 'quickRestore',
                                label: 'Restore',
                                iconClass: 'ph ph-arrow-counter-clockwise',
                                data: { id: this.model.id }
                            });
                        }
                    }
                } else if (actionName === 'deletePermanently') {
                    if (filters && filters?.bool?.onlyDeleted === true) {
                        if (this.model.get('_meta')?.permissions?.delete) {
                            list.push({
                                action: 'deletePermanently',
                                label: 'deletePermanently',
                                iconClass: 'ph ph-trash',
                                data: { id: this.model.id }
                            });
                        }
                    }
                } else if (actionName === 'quickView') {
                    list.push({
                        action: 'quickView',
                        label: 'View',
                        iconClass: 'ph ph-eye',
                        data: { id: this.model.id },
                        link: '#' + this.model.name + '/view/' + this.model.id
                    });
                } else if (actionName === 'quickEdit') {
                    if (this.model.get('_meta')?.permissions?.edit) {
                        list.push({
                            action: 'quickEdit',
                            label: 'Edit',
                            iconClass: 'ph ph-pencil-simple',
                            data: { id: this.model.id },
                            link: '#' + this.model.name + '/edit/' + this.model.id
                        });
                    }
                } else if (actionName === 'select') {
                    if (!this.getMetadata().get(['scopes', this.model.name, 'selectionDisabled']) && this.getAcl().check('Selection', 'create')) {
                        list.push({
                            action: 'select',
                            label: 'Select',
                            iconClass: 'ph ph-check',
                            data: { id: this.model.id }
                        });
                    }
                } else if (actionName === 'quickCompare') {
                    if (this.getMetadata().get(['clientDefs', this.model.name, 'showCompareAction'])) {
                        let instances = this.getMetadata().get(['app', 'comparableInstances']);
                        if (instances.length) {
                            list.push({
                                action: 'quickCompare',
                                label: this.translate('Compare with ' + instances[0].name),
                                name: 'compare',
                                iconClass: 'ph ph-arrows-left-right',
                                data: { id: this.model.id, scope: this.model.name },
                            });
                        }
                    }
                } else if (actionName === 'quickRemove') {
                    if (this.model.get('_meta')?.permissions?.delete) {
                        list.push({
                            action: 'quickRemove',
                            label: 'Remove',
                            iconClass: 'ph ph-trash-simple',
                            data: { id: this.model.id }
                        });
                    }
                } else {
                    let actionData = this.getMetadata().get(['clientDefs', scope, 'listActions', actionName]);
                    if (actionData && this.model.get('_meta')?.permissions?.[actionName]) {
                        list.push({
                            action: actionData.action || 'universalAction',
                            iconClass: actionData.iconClass || undefined,
                            iconUrl: actionData.iconUrl || undefined,
                            label: this.translate(actionName, 'actions', scope),
                            data: {
                                'name': actionName,
                                'id': this.model.id
                            }
                        })
                    }
                }
            });

            return list;
        }
    });
});
