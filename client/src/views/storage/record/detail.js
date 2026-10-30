/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * The record of a storage type, the scan is executed for the storage record, it is the supertype record.
 */
Espo.define('views/storage/record/detail', 'views/subtype/record/detail', Dep => {

    return Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            this.additionalButtons.push({
                "action": "scan",
                "label": this.translate('scan', 'labels', 'Storage')
            });
        },

        actionScan() {
            this.notify('Please wait...');
            this.ajaxPostRequest(`Storage/${this.model.get('supertypeId')}/createScanJob`).success(() => {
                this.notify(this.translate('jobCreated'), 'success');
            });
        },

    });
});
