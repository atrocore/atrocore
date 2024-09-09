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

Espo.define('views/export/modals/export', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        cssName: 'export-modal',

        template: 'export/modals/export',

        data: function () {
            return {};
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'export',
                    label: 'Export',
                    style: 'danger'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.model = new Model();
            this.model.name = 'Export';

            this.scope = this.options.scope;

            this.ajaxGetRequest('ExportFeed', {
                select: 'id,name',
                where: [
                    {
                        attribute: "isActive",
                        type: "isTrue"
                    },
                    {
                        attribute: "data",
                        type: "like",
                        value: `%"entity":"${this.scope}"%`
                    }
                ]
            }, {async: false}).then(function (data) {
                this.createView('record', 'views/export/record/record', {
                    scope: this.scope,
                    model: this.model,
                    exportFeeds: data.list,
                    el: this.getSelector() + ' .record'
                });
            }.bind(this));
        },

        actionExport: function () {
            if (!this.model.get('useExistingExportFeed') && !this.model.get('exportAllField') && !this.model.get('fieldList')?.length) {
                this.notify(this.translate('noFieldSelected', 'messages', 'ExportFeed'), 'error');
                return
            }

            if (this.model.get('useExistingExportFeed') && !this.model.get('exportFeed')) {
                this.notify(this.translate('noExportFeedSelected', 'messages', 'ExportFeed'), 'error');
                return
            }

            let data = {
                id: this.model.get('exportFeed'),
                fileType: this.model.get('fileType'),
                exportAllField: this.model.get('exportAllField'),
                fieldList: this.model.get('fieldList'),
                ignoreFilter: this.model.get('ignoreFilter'),
                scope: this.scope,
                entityFilterData: this.options.entityFilterData
            };

            let actionName = this.model.get('useExistingExportFeed') ? 'exportFile' : 'directExportFile'

            this.ajaxPostRequest(`ExportFeed/action/${actionName}`, data).then(response => {
                if (response) {
                    this.notify(this.translate('jobCreated'), 'success');
                } else {
                    this.notify(this.translate('jobNotCreated'), 'danger');
                }
            });

            this.close();
        }

    });
});