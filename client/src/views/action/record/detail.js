/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/detail', ['views/record/detail', 'views/record/panels/entity-filter-result'],
    (Dep, EntityFilter) => Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            this.additionalButtons.push({
                "action": "execute",
                "label": this.translate('execute', 'labels', 'Action'),
                "disabled": !this.canExecute()
            });
        },

        canExecute() {
            const type = this.model.get('type');
            return this.model.get('isActive')
                && type !== 'suggestValueByAi'
                && type !== 'suggestValue'
                && type !== 'error';
        },

        actionExecute() {
            if (!this.canExecute()) {
                return;
            }

            this.confirm(this.translate('executeNow', 'messages', 'Action'), () => {
                this.notify('Please wait...');
                const type = this.model.get('type');
                const inBackground = this.model.get('inBackground');
                const urlSuffix = inBackground ? `${type}Async` : type;
                this.ajaxPostRequest(`Action/${this.model.get('id')}/${urlSuffix}`, {}).success(response => {
                    if (response.jobId) {
                        this.notify(this.translate('jobAdded', 'messages'), 'success');
                    } else {
                        if (response.success) {
                            this.notify(response.message, 'success');
                        } else {
                            this.notify(response.message, 'error');
                        }
                    }
                    setTimeout(() => {
                        $('.action[data-action=refresh][data-panel=executions]').click();
                    }, 2000)
                });
            });
        },

        actionOpenSearchFilter() {
            EntityFilter.prototype.actionOpenSearchFilter.call(this);
        },

        buttonVisible() {
            return this.model.get('targetEntity')
                && this.getAllowedActionTypes().includes(this.model.get('type'))
                && !this.model.get('applyToPreselectedRecords');
        },

        getAllowedActionTypes() {
            return ['update', 'delete', 'email'];
        }
    })
);
