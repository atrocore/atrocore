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
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            (this.getModels() || [])
                .forEach(model => {
                    const meta = model.item?.get('_meta')?.cluster || {};

                    if (meta.confirmed) {
                        this.$el.find(`th[data-id="${model.id}"]`).addClass('confirmed');
                    }

                    if (meta.golden) {
                        this.$el.find(`th[data-id="${model.id}"]`).addClass('golden');
                    }
                });
        },

        getModels() {
            const models = Dep.prototype.getModels.call(this) || [];

            return models
                .sort((a, b) => {
                    const aMeta = a.item?.get('_meta')?.cluster || {};
                    const bMeta = b.item?.get('_meta')?.cluster || {};

                    if (!!aMeta.confirmed && !!!bMeta.confirmed) return -1;
                    if (!!!aMeta.confirmed && !!bMeta.confirmed) return 1;
                    return 0;
                })
                .sort((a, b) => a.item?.get('_meta')?.cluster?.golden ? -1 : 1);
        }
    })
})