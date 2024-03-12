/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/detail-side', 'views/record/detail-side',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.panelList.unshift({
                "name": "preview",
                "label": "Preview",
                "view": "views/file/record/panels/side/preview/main"
            });
        }
    })
);