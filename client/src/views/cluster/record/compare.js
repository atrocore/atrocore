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

        setup: function () {
            this.clusterModel = this.options.model;

            Dep.prototype.setup.call(this);
        },

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

            (this.options.models || [])
                .filter(model => !!model.get('masterRecordId'))
                .forEach(model => {
                    this.$el.find(`th[data-id="${model.id}"]`).addClass('confirmed');
                });

            if (this.clusterModel.get('goldenRecordId')) {
                this.$el.find(`th[data-id="${this.clusterModel.get('goldenRecordId')}"]`).addClass('golden');
            }
        },

        getModels() {
            const models = Dep.prototype.getModels.call(this);

            return models
                .sort((a, b) => {
                    const aHasMaster = !!a.get('masterRecordId');
                    const bHasMaster = !!b.get('masterRecordId');

                    if (aHasMaster && !bHasMaster) return -1;
                    if (!aHasMaster && bHasMaster) return 1;
                    return 0;
                })
                .sort((a, b) => a.get('id') === this.clusterModel.get('goldenRecordId') ? -1 : 1);
        }
    })
})