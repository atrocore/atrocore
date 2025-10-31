/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/merge', 'views/selection/record/detail/compare', function (Dep) {

    return Dep.extend({
        merging: true,

        actionMerge()  {
            this.applyMerge();
        }
    });
});
