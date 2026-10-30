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
 * The record of a connection type, the actions are executed for the connection record, it is the supertype record.
 */
Espo.define('views/connection/record/detail', 'views/subtype/record/detail', function (Dep) {

    return Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            this.additionalButtons = [
                {
                    "action": "testConnection",
                    "label": this.translate('testConnection', 'labels', 'Connection')
                }
            ];
        },

        actionTestConnection() {
            this.notify('Loading...');
            this.ajaxPostRequest(`Connection/${this.model.get('supertypeId')}/test`).then(() => {
                this.notify(this.translate('connectionSuccess', 'labels', 'Connection'), 'success');
            });
        }
    });
});
