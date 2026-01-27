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

            let list = [];

            let quickView = true;

            if (this.model.get('_meta')?.permissions?.quickView === false) {
                quickView = false;
            }

            if (quickView) {
                list.push({
                    action: 'quickView',
                    label: 'View',
                    data: {
                        id: this.model.id
                    },
                    link: '#' + this.model.name + '/view/' + this.model.id
                })
            }

            // if entity can be open in tab
            if (this.model.get('hasOpen') && this.model.get('downloadUrl')) {
                list.push({
                    action: 'openInTab',
                    label: 'Open',
                    data: {
                        url: this.model.get('downloadUrl')
                    },
                });
            }

            if (this.model.get('_meta')?.permissions?.edit) {
                if (this.model.name === 'File') {
                    list.push({
                        action: 'reupload',
                        label: 'Reupload',
                        data: {
                            id: this.model.get('id')
                        },
                    });
                }
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id,
                        cid: this.model.cid
                    },
                    link: '#' + this.model.name + '/edit/' + this.model.id
                });

                if (this.model.has('isInherited') && !this.model.get('isInherited')) {
                    list.push({
                        action: 'inheritRelated',
                        label: 'inherit',
                        data: {
                            id: this.model.id,
                            cid: this.model.cid
                        }
                    });
                }
            }

            if (this.model.get('_meta')?.permissions?.unlink) {
                list.push({
                    action: 'unlinkRelated',
                    label: 'Unlink',
                    data: {
                        id: this.model.id,
                        cid: this.model.cid
                    }
                });
            }

            if (this.model.get('_meta')?.permissions?.delete) {
                list.push({
                    action: 'removeRelated',
                    label: 'Delete',
                    data: {
                        id: this.model.id,
                        cid: this.model.cid
                    }
                });
            }

            (this.getMetadata().get(['clientDefs', parentModelName, 'relationshipPanels', relationName, 'customActions']) || [])
                .forEach(action => {
                    if(!action.name) {
                        return;
                    }
                    if(this.model.get('_meta')?.permissions?.[action.name]) {
                        list.push({
                            action: 'customAction',
                            label: this.translate(action.name, 'customActions', parentModelName),
                            data: {
                                'parent-scope': parentModelName,
                                'name': action.name,
                                'id': this.model.id,
                                'cid': this.model.cid
                            }
                        })
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
