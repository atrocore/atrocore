/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/cluster/record/compare', 'views/selection/record/detail/compare', function (Dep) {
    return Dep.extend({

        itemScope: 'ClusterItem',

        relationName: 'clusterItems',

        isComparisonAcrossScopes() {
            return false;
        },

        actionRejectItem(e) {
            const id = $(e.currentTarget).data('selection-item-id');

            this.ajaxPostRequest(`ClusterItem/action/reject`, {id: id})
                .then(response => {
                    this.notify('Item rejected', 'success');
                    this.notify(this.translate('Loading...'));

                    const view = this.getParentView();
                    view.reloadModels(() => view.refreshContent());
                })
        }
    })
})