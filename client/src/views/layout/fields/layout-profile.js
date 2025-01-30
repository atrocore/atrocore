/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/layout-profile', 'views/fields/link', function (Dep) {

    return Dep.extend({
        selectBoolFilterList: ['withLayouts'],

        boolFilterData: {
            withLayouts() {
                return this.options.withLayoutsParams
            }
        },

    });
});

