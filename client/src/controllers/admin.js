

Espo.define('controllers/admin', ['controller', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        checkAccessGlobal: function () {
            if (this.getUser().isAdmin()) {
                return true;
            }
            return false;
        },

        index: function () {
            this.main('views/admin/index', null);
        },

        layouts: function (options) {
            var scope = options.scope || null;
            var type = options.type || null;

            this.main('views/admin/layouts/index', {scope: scope, type: type});
        },

        labelManager: function (options) {
            var scope = options.scope || null;
            var language = options.language || null;

            this.main('views/admin/label-manager/index', {scope: scope, language: language});
        },

        fieldManager: function (options) {
            var scope = options.scope || null;
            var field = options.field || null;

            this.main('views/admin/field-manager/index', {scope: scope, field: field});
        },

        entityManager: function (options) {
            var scope = options.scope || null;

            this.main('views/admin/entity-manager/index', {scope: scope});
        },

        linkManager: function (options) {
            var scope = options.scope || null;

            this.main('views/admin/link-manager/index', {scope: scope});
        },

        upgrade: function (options) {
            this.main('views/admin/upgrade/index');
        },

        getSettingsModel: function () {
            var model = this.getConfig().clone();
            model.defs = this.getConfig().defs;

            return model;
        },

        settings: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/settings',
                    recordView: 'views/admin/settings'
                });
            }, this);
            model.fetch();
        },

        notifications: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/notifications',
                    recordView: 'views/admin/notifications'
                });
            }, this);
            model.fetch();
        },

        outboundEmails: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/outbound-emails',
                    recordView: 'views/admin/outbound-emails'
                });
            }, this);
            model.fetch();
        },

        inboundEmails: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/inbound-emails',
                    recordView: 'views/admin/inbound-emails'
                });
            }, this);
            model.fetch();
        },

        currency: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/currency',
                    recordView: 'views/admin/currency'
                });
            }, this);
            model.fetch();
        },

        authTokens: function () {
            this.collectionFactory.create('AuthToken', function (collection) {
                var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime());
                searchManager.loadStored();
                collection.where = searchManager.getWhere();
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;

                this.main('views/admin/auth-token/list', {
                    scope: 'AuthToken',
                    collection: collection,
                    searchManager: searchManager
                });
            }, this);
        },

        authLog: function () {
            this.collectionFactory.create('AuthLogRecord', function (collection) {
                var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime());
                searchManager.loadStored();
                collection.where = searchManager.getWhere();
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;

                this.main('views/admin/auth-log-record/list', {
                    scope: 'AuthLogRecord',
                    collection: collection,
                    searchManager: searchManager
                });
            }, this);
        },

        jobs: function () {
            this.collectionFactory.create('Job', function (collection) {
                var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime());
                searchManager.loadStored();
                collection.where = searchManager.getWhere();
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;

                this.main('views/admin/job/list', {
                    scope: 'Job',
                    collection: collection,
                    searchManager: searchManager,
                });
            }, this);
        },

        userInterface: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/user-interface',
                    recordView: 'views/admin/user-interface'
                });
            }, this);
            model.fetch();
        },

        authentication: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'admin/settings/headers/authentication',
                    recordView: 'views/admin/authentication'
                });
            }, this);
            model.fetch();
        },

        integrations: function (options) {
            var integration = options.name || null;

            this.main('views/admin/integrations/index', {integration: integration});
        },

        clearCache: function (options) {
            var master = this.get('master');
            Espo.Ui.notify(master.translate('Please wait...'));
            this.getRouter().navigate('#Admin');
            $.ajax({
                url: 'Admin/clearCache',
                type: 'POST',
                success: function () {
                    var msg = master.translate('Cache has been cleared', 'labels', 'Admin');
                    Espo.Ui.success(msg);
                }.bind(this)
            });
        }
    });

});
