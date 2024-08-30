/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.getUser().isAdmin()) {
                this.menu.buttons.push({
                    name: 'layouts',
                    label: 'Layouts',
                    style: 'default',
                    action: "layouts",
                    link: '#Admin/layouts?layoutProfileId=' + this.model.get('id')
                });
            }
        },

        actionPreferences: function () {
            this.getRouter().navigate('#Admin/layouts?layoutProfileId=' + this.model.get('id'), {trigger: true});
        },
    });
});

