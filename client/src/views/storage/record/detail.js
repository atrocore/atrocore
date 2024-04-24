/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/storage/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [
                {
                    "action": "scan",
                    "label": this.translate('scan', 'labels', 'Storage')
                }
            ];
        },

        actionScan() {
            this.notify('Please wait...');
            this.ajaxPostRequest('Storage/action/createScanJob', {id: this.model.get('id')}).success(() => {
                this.notify(this.translate('jobCreated'), 'success');
            });
        },

    });
});
