/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/queue-manager/actions/abstract-action', 'view',
    Dep => Dep.extend({

        template: 'queue-manager/actions/abstract-action',

        buttonLabel: '',

        actionData: {},

        disabled: false,

        events: {
            'click [data-action="runAction"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (this.canRun()) {
                    this.runAction();
                }
            },
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.actionData = this.options.actionData || this.actionData;
        },

        data() {
            return {
                buttonLabel: this.buttonLabel,
                disabled: this.disabled
            };
        },

        runAction() {
            //run action
        },

        canRun() {
            return !this.disabled;
        }

    })
);

