/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/record/detail-small', 'class-replace!treo-core:views/record/detail-small',
    Dep => Dep.extend({
        isWide: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.isWide = this.isWide || this.sideDisabled;
        },

    })
);