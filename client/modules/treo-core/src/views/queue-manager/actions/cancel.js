/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/actions/cancel', 'treo-core:views/queue-manager/actions/abstract-action',
    Dep => Dep.extend({

        buttonLabel: 'cancel',

        getSaveData() {
            return {
                status: 'Canceled'
            };
        },

        runAction() {
            this.ajaxPutRequest(`${this.model.name}/${this.model.id}`, this.getSaveData())
                .then(() => this.model.trigger('reloadList'));
        },

    })
);

