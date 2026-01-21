/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/navigation/record/row-actions/selection-item', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        getActionList: function () {
          return [
              {
                  action: 'remove',
                  label: 'Remove',
                  data: {
                      "selection-item-id": this.model._selectionItemId,
                  }
              }
          ]
        }
    })
});
