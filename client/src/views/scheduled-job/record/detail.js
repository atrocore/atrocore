/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/scheduled-job/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            if (this.model.id && this.model.get('isActive')){
                this.additionalButtons.push({
                    action: 'executeNow',
                    label: this.translate('executeNow', 'labels', 'ScheduledJob')
                })
            }
        },

        actionExecuteNow() {
            this.ajaxPostRequest(`ScheduledJob/${this.model.id}/executeNow`).then(response => {
                this.notify(this.translate(response ? 'jobLaunched' : 'jobAlreadyExist', 'messages', 'ScheduledJob'), response ? 'success' : 'danger');
                $('button.action[data-action="refresh"][data-panel="jobs"]').click();
            });
        }
    });

});

