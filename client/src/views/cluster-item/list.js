/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/cluster-item/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        actionMove: function (data) {
            data = data || {};
            var id = data.id;
            if (!id) return;

            this._openClusterSelectModal({id: id});
        },

        massActionMove: function () {
            var params = {};
            if (this.allResultIsChecked) {
                params.where = this.collection.getWhereForCheckedRecords();
            } else {
                params.idList = this.checkedList;
            }
            this._openClusterSelectModal(params);
        },

        _openClusterSelectModal: function (params) {
            this.notify('Loading...');
            this.createView('selectCluster', 'views/modals/select-records', {
                scope: 'Cluster',
                multiple: false,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false
            }, function (view) {
                view.render();
                this.notify(false);

                this.listenToOnce(view, 'select', function (model) {
                    var targetClusterId = model.id;
                    var body = Object.assign({targetClusterId: targetClusterId}, params);

                    this.notify(this.translate('Loading...'));
                    this.ajaxPostRequest('ClusterItem/action/move', body)
                        .then(() => {
                            this.notify(this.translate('Done'), 'success');
                            this.collection.fetch();
                        })
                        .catch(() => {
                            this.notify(this.translate('Error occured'), 'error');
                        });
                }, this);
            }.bind(this));
        }

    });
});
