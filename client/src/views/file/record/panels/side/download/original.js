/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/download/original', 'view',
    Dep => {
        return Dep.extend({
            template: "file/record/panels/side/download/original",
            active: true,

            setup() {

            },

            hide() {
                this.active = false;
            },

            show() {
                this.active = true;
            },

            buildUrl() {
                return this.model.get('downloadUrl')
            }
        });
    }
);