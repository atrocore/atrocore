
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/scheduled-job/list', ['class-replace!treo-core:views/list', 'views/list'], function (Dep, List) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                link: '#Admin/jobs',
                html: this.translate('Jobs', 'labels', 'Admin')
            });
        },

        afterRender: function () {
            List.prototype.afterRender.call(this);
        },

    });

});
