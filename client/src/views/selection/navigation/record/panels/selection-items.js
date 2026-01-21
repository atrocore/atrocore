/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/selection/navigation/record/panels/selection-items', 'views/record/list-expanded', function (Dep) {

    return Dep.extend({

        headerDisabled: true,

        massActionsDisabled: true,

        rowActionsView: 'views/selection/navigation/record/row-actions/selection-item',

        showCount: false,

        showMore: false,

        buttonsDisabled: true,

        checkboxes: false,

        actionRemove(data) {
            if(data.selectionItemId) {
                this.notify(this.translate('removing'))
                $.ajax({
                    url: `SelectionItem/${data.selectionItemId}`,
                    type: 'DELETE',
                    contentType: 'application/json',
                    success: () => {
                       this.getParentView().afterSelectionItemRemoved(data.selectionItemId);
                       this.notify(this.translate('Success'), 'success')
                    }
                });
            }
        }
    });
});