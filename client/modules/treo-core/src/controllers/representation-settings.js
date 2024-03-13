/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:controllers/representation-settings', 'controllers/admin', function (Dep, ) {

    return Dep.extend({

        defaultAction: 'representationSettings',

        representationSettings: function () {
            var model = this.getSettingsModel();

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'treo-core:admin/settings/headers/representation-settings',
                    recordView: 'views/admin/settings',
                    layoutName: 'representationSettingsList',
                    optionsToPass: ['layoutName']
                });
            }, this);
            model.fetch();
        },

    });

});
