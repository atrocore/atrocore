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

Espo.define('views/record/row-actions/relationship', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            const parentModelName = this.options?.parentModelName || null;
            const relationName = this.options?.relationName || null;

            let actionsSortOrder = {
                "quickView": 110,
                "openInTab": 120,
                "reupload": 130,
                "quickEdit": 140,
                "inheritRelated": 150,
                "unlinkRelated": 160,
                "removeRelated": 170
            };

            $.each(this.getMetadata().get(['clientDefs', parentModelName, 'relationshipPanels', relationName, 'actions']) || {}, (actionName, actionData) => {
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
                if (actionName === 'quickView') {
                    if (!(this.model.get('_meta')?.permissions?.quickView === false)) {
                        list.push({
                            action: 'quickView',
                            label: 'View',
                            data: {
                                id: this.model.id
                            },
                            link: '#' + this.model.name + '/view/' + this.model.id
                        })
                    }
                } else if (actionName === 'openInTab') {
                    if (this.model.get('hasOpen') && this.model.get('downloadUrl')) {
                        list.push({
                            action: 'openInTab',
                            label: 'Open',
                            data: {
                                url: this.model.get('downloadUrl')
                            },
                        });
                    }
                } else if (actionName === 'reupload') {
                    if (this.model.name === 'File' && this.model.get('_meta')?.permissions?.edit) {
                        list.push({
                            action: 'reupload',
                            label: 'Reupload',
                            data: {
                                id: this.model.get('id')
                            },
                        });
                    }
                } else if (actionName === 'quickEdit') {
                    if (this.model.get('_meta')?.permissions?.edit) {
                        list.push({
                            action: 'quickEdit',
                            label: 'Edit',
                            data: {
                                id: this.model.id,
                                cid: this.model.cid
                            },
                            link: '#' + this.model.name + '/edit/' + this.model.id
                        });
                    }
                } else if (actionName === 'inheritRelated') {
                    if (this.model.has('isInherited') && !this.model.get('isInherited') && this.model.get('_meta')?.permissions?.edit) {
                        list.push({
                            action: 'inheritRelated',
                            label: 'inherit',
                            data: {
                                id: this.model.id,
                                cid: this.model.cid
                            }
                        });
                    }
                } else if (actionName === 'unlinkRelated') {
                    if (this.model.get('_meta')?.permissions?.unlink) {
                        list.push({
                            action: 'unlinkRelated',
                            iconClass: "ph ph-link-break",
                            label: 'Unlink',
                            data: {
                                id: this.model.id,
                                cid: this.model.cid
                            }
                        });
                    }
                } else if (actionName === 'removeRelated') {
                    if (this.model.get('_meta')?.permissions?.delete) {
                        list.push({
                            action: 'removeRelated',
                            iconClass: 'ph ph-trash-simple',
                            label: 'Delete',
                            data: {
                                id: this.model.id,
                                cid: this.model.cid
                            }
                        });
                    }
                } else {
                    let actionData = this.getMetadata().get(['clientDefs', parentModelName, 'relationshipPanels', relationName, 'actions', actionName]);
                    if (actionData && this.model.get('_meta')?.permissions?.[actionName]) {
                        list.push({
                            action: actionData.action || 'universalAction',
                            iconClass: actionData.iconClass,
                            label: this.translate(actionName, 'actions', parentModelName),
                            data: {
                                'name': actionName,
                                'id': this.model.id,
                                'cid': this.model.cid,
                                'parent-scope': parentModelName,
                                'relation-name': relationName
                            }
                        })
                    }
                }
            });

            list.push({
                divider: true
            });

            list.push({
                preloader: true
            });

            return list;
        }
    });
});
