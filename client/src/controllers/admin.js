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

Espo.define('controllers/admin', ['controller', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        checkAccessGlobal: function () {
            return this.getUser().isAdmin();
        },

        index: function () {
            this.main('views/admin/index');
        },

        layouts: function (options) {
            const { scope, type } = options;
            this.main('views/admin/layouts/index', { scope, type });
        },

        fieldManager: function (options) {
            const { scope, field } = options;
            this.main('views/admin/field-manager/index', { scope, field });
        },

        entityManager: function (options) {
            const { scope } = options;
            this.main('views/admin/entity-manager/index', { scope });
        },

        linkManager: function (options) {
            const { scope } = options;
            this.main('views/admin/link-manager/index', { scope });
        },

        getSettingsModel: function () {
            const model = this.getConfig().clone();
            model.defs = this.getConfig().defs;
            return model;
        },

        loadSettingsView: function (view, model, headerTemplate, recordView) {
            model.once('sync', function () {
                model.id = '1';
                this.main(view, {
                    model,
                    headerTemplate,
                    recordView
                });
            }, this);
            model.fetch();
        },

        settings: function () {
            const model = this.getSettingsModel();
            this.loadSettingsView('views/settings/edit', model, 'admin/settings/headers/settings', 'views/admin/settings');
        },

        moduleSettings: function (options) {
            const { id } = options;
            const view = id.replaceAll('_', '/');
            const model = this.getSettingsModel();
            model.once('sync', function () {
                model.id = id;
                this.main(view, { model });
            }, this);
            model.fetch();
        },

        notifications: function () {
            const model = this.getSettingsModel();
            this.loadSettingsView('views/settings/edit', model, 'admin/settings/headers/notifications', 'views/admin/notifications');
        },

        outboundEmails: function () {
            const model = this.getSettingsModel();
            this.loadSettingsView('views/settings/edit', model, 'admin/settings/headers/outbound-emails', 'views/admin/outbound-emails');
        },

        authTokens: function () {
            this.collectionFactory.create('AuthToken', (collection) => {
                const searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime());
                searchManager.loadStored();
                collection.where = searchManager.getWhere();
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;

                this.main('views/admin/auth-token/list', {
                    scope: 'AuthToken',
                    collection,
                    searchManager
                });
            }, this);
        },

        authLog: function () {
            this.collectionFactory.create('AuthLogRecord', (collection) => {
                const searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime());
                searchManager.loadStored();
                collection.where = searchManager.getWhere();
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;

                this.main('views/admin/auth-log-record/list', {
                    scope: 'AuthLogRecord',
                    collection,
                    searchManager
                });
            }, this);
        },

        jobs: function () {
            this.collectionFactory.create('Job', (collection) => {
                const searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime());
                searchManager.loadStored();
                collection.where = searchManager.getWhere();
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;

                this.main('views/admin/job/list', {
                    scope: 'Job',
                    collection,
                    searchManager
                });
            }, this);
        },

        userInterface: function () {
            const model = this.getSettingsModel();
            this.loadSettingsView('views/settings/edit', model, 'admin/settings/headers/user-interface', 'views/admin/user-interface');
        },

        authentication: function () {
            const model = this.getSettingsModel();
            this.loadSettingsView('views/settings/edit', model, 'admin/settings/headers/authentication', 'views/admin/authentication');
        },

        clearCache: function () {
            const master = this.get('master');
            Espo.Ui.notify(master.translate('Please wait...'));
            this.getRouter().navigate('#Admin');
            $.ajax({
                url: 'Admin/clearCache',
                type: 'POST',
                success: () => {
                    const msg = master.translate('Cache has been cleared', 'labels', 'Admin');
                    Espo.Ui.success(msg);
                }
            });
        },

        rebuildDb: function () {
            const master = this.get('master');
            this.getRouter().navigate('#Admin');
            Espo.Ui.confirm(master.translate('rebuildDb', 'messages', 'Admin'), {
                confirmText: master.translate('Apply'),
                cancelText: master.translate('Cancel')
            }, () => {
                $.ajax({
                    url: 'Admin/rebuildDb',
                    type: 'POST',
                    success: () => {
                        Espo.Ui.success('Success');
                    }
                });
            });
        }

    });

});
