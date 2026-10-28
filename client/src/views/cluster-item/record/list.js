/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/cluster-item/record/list', 'views/record/list', Dep => Dep.extend({

    setup() {
        Dep.prototype.setup.call(this);

        if (this.massActionList.length && this.getCluster() && this.getAcl().check('Cluster', 'edit')) {
            this.massActionList.unshift('consolidate', 'deconsolidate');
        }
    },

    getMassActions() {
        const actions = Dep.prototype.getMassActions.call(this);

        if (this.allResultIsChecked) {
            return actions;
        }

        const checkedItems = (this.checkedList || []).map(id => this.collection.get(id)).filter(Boolean);

        return actions.filter(action => {
            const permission = action === 'remove' ? 'delete' : action;

            return checkedItems.every(item => item.get('_meta')?.availableActions?.[permission] !== false);
        });
    },

    getCluster() {
        const model = this.options.panelView?.model;

        return model && model.name === 'Cluster' ? model : null;
    },

    massActionConsolidate() {
        this.runClusterMassAction('consolidate');
    },

    massActionDeconsolidate() {
        this.runClusterMassAction('deconsolidate');
    },

    runClusterMassAction(action) {
        const clusterItemsIds = this.checkedList.slice();

        if (!clusterItemsIds.length) {
            return;
        }

        this.notify(this.translate('pleaseWait', 'messages'));

        this.ajaxPostRequest(`Cluster/${this.getCluster().id}/${action}`, {clusterItemsIds}).then(() => {
            this.trigger('refresh');
            this.collection.fetch().then(() => {
                this.notify(this.translate(action, 'massActionSuccessMessages', 'ClusterItem'), 'success');
            });
        });
    }

}));
