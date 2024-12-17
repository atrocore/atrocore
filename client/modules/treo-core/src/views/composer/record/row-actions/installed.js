/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/record/row-actions/installed', 'views/record/row-actions/default',
    Dep => Dep.extend({

        disableActions: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model.collection, 'disableActions', (disableActions) => {
                this.disableActions = disableActions;
                this.reRender();
            });
        },

        getActionList() {
            let list = [];
            if (!this.disableActions && this.model.get('isComposer')) {
                if (this.model.get('status') === 'update' || !this.model.get('status')) {
                    list.push({
                        action: 'updateModule',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        }
                    });
                }

                list.push({
                    action: 'showReleaseNotes',
                    label: 'showReleaseNotes',
                    data: {
                        id: this.model.id
                    }
                });

                if (this.model.get('status')) {
                    list.push({
                        action: 'cancelModule',
                        label: 'cancelModule',
                        data: {
                            id: this.model.id,
                            status: this.model.get('status')
                        }
                    });
                }

                if (!['install', 'delete'].includes(this.model.get('status')) && !this.model.get('isSystem')) {
                    list.push({
                        action: 'removeModule',
                        label: 'Delete',
                        data: {
                            id: this.model.id
                        }
                    });
                }
            }

            return list;
        },

    })
);
