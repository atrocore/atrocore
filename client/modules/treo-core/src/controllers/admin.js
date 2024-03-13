/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:controllers/admin', 'class-replace!treo-core:controllers/admin', function (Dep) {
    return Dep.extend({
        error404: function () {
            this.entire('views/base', {template: 'errors/404'}, function (view) {
                view.render();
            });
        },
        currency: function () {
            // blocking page
            this.error404();
        },

        settings: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'treo-core:admin/settings/headers/settings',
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
                    headerTemplate: 'treo-core:admin/settings/headers/notifications',
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
                    headerTemplate: 'treo-core:admin/settings/headers/outbound-emails',
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
                    headerTemplate: 'treo-core:admin/settings/headers/inbound-emails',
                    recordView: 'views/admin/inbound-emails'
                });
            }, this);
            model.fetch();
        },

        userInterface: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'treo-core:admin/settings/headers/user-interface',
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
                    headerTemplate: 'treo-core:admin/settings/headers/authentication',
                    recordView: 'views/admin/authentication'
                });
            }, this);
            model.fetch();
        },
    });

});
