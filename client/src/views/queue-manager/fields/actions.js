/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/queue-manager/fields/actions', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'queue-manager/fields/actions/list',

        defaultActionDefs: {
            view: 'views/queue-manager/actions/show-message'
        },

        data() {
            return {
                actions: [{type: "cancel"}]
            };
        },

        afterRender() {
            this.buildActions();
        },

        buildActions() {
            if (this.model.get('status') === 'Pending' || this.model.get('status') === 'Running') {
                let actionDefs = this.getMetadata().get(['clientDefs', 'Job', 'queueActions', 'cancel']) || this.defaultActionDefs;
                if (actionDefs.view && this.getAcl().check(this.model, actionDefs.acl)) {
                    this.createView('cancel', actionDefs.view, {
                        el: `${this.options.el} .queue-manager-action[data-type="cancel"]`,
                        actionData: {},
                        model: this.model
                    }, view => {
                        this.listenTo(view, 'reloadList', () => {
                            this.model.trigger('reloadList');
                        });
                        view.render();
                    });
                }
            }
        }

    })
);

