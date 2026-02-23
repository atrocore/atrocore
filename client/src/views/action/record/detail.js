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

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [{
                "action": "execute",
                "label": this.translate('execute', 'labels', 'Action')
            }];

            this.listenTo(this.model, 'after:save', () => {
                this.handleButtonsDisability();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.handleButtonsDisability();
        },

        isButtonsDisabled() {
            return !this.model.get('isActive');
        },

        handleButtonsDisability() {
            if (this.isButtonsDisabled()) {
                $('.additional-button').addClass('disabled');
            } else {
                $('.additional-button').removeClass('disabled');
            }
        },

        actionExecute() {
            if (this.isButtonsDisabled()) {
                return;
            }

            this.confirm(this.translate('executeNow', 'messages', 'Action'), () => {
                this.notify('Please wait...');
                this.ajaxPostRequest('Action/action/executeNow', {actionId: this.model.get('id')}).success(response => {
                    if (response.inBackground) {
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