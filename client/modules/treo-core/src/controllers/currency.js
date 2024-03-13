/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:controllers/currency', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: "view",

        view: function () {
            // get model
            var model = this.getConfig().clone();
            model.defs = this.getConfig().defs;

            model.once('sync', function () {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'treo-core:admin/settings/headers/currency',
                    recordView: 'views/admin/currency'
                });
            }, this);
            model.fetch();
        },
    });
});
