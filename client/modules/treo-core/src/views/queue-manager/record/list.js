/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/record/list', 'views/record/list', Dep => {

    return Dep.extend({

        massActionList: ['remove', 'cancel'],

        checkAllResultMassActionList: ['remove', 'cancel'],

        massActionCancel() {
            if (!this.getAcl().check(this.entityType, 'edit')) {
                this.notify('Access denied', 'error');
                return false;
            }

            let data = {};
            if (!this.allResultIsChecked) {
                data.ids = this.checkedList;
            } else {
                data.where = this.collection.getWhere();
            }

            this.notify(this.translate('loading', 'messages'));
            this.ajaxPostRequest('QueueItem/action/massCancel', data).then(response => {
                if (response) {
                    this.notify('Done', 'success');
                    this.collection.fetch();
                } else {
                    this.notify('Error occurred', 'error');
                }
            });
        },

    });

});
