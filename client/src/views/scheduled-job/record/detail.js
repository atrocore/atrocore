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

        setup() {
            Dep.prototype.setup.call(this);
            this.additionalButtons = [
                {
                    action: 'executeNow',
                    label: this.translate('executeNow', 'labels', 'ScheduledJob')
                }
            ];

            this.listenTo(this.model, 'after:save', () => {
                this.handleExecuteNowButtonDisability()
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.handleExecuteNowButtonDisability();
        },

        handleExecuteNowButtonDisability() {
            const $buttons = $('.additional-button[data-action="executeNow"]');
            if (this.hasExecuteNow()) {
                $buttons.removeClass('disabled');
            } else {
                $buttons.addClass('disabled');
            }
        },

        hasExecuteNow() {
            return this.model.get('status') === 'Active';
        },

        actionExecuteNow() {
            if (!this.hasExecuteNow()) {
                return;
            }
            this.ajaxPostRequest('ScheduledJob/action/executeNow', {id: this.model.id}).then(response => {
                this.notify(this.translate(response ? 'jobLaunched' : 'jobAlreadyExist', 'messages', 'ScheduledJob'), response ? 'success' : 'danger');
                $('button.action[data-action="refresh"][data-panel="log"]').click();
            });
        }
    });

});

