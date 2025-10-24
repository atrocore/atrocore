/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/panels/selection-record/merge', 'views/selection/record/panels/selection-record/compare', function (Dep) {

    return Dep.extend({
        merging: true,

        actionMerge()  {
            this.applyMerge();
        },

        getActionList() {
            if(!this.merging) {
                return [];
            }
            return [
                {
                    label: 'Merge',
                    action: 'merge',
                    name: 'selectionRecords'
                }
            ];
        }
    });
});
