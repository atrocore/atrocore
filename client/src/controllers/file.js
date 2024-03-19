/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('controllers/file', 'controllers/record',
    Dep => {

        return Dep.extend({

            defaultAction: 'list',

            doAction(action, options) {
                action = action ? action : this.getStorage().get('list-view', this.name);

                Dep.prototype.doAction.call(this, action, options);
            },

            beforePlate() {
                this.handleCheckAccess('read');
            },

            plate() {
                this.getCollection(function (collection) {
                    this.main(this.getViewName('plate'), {
                        scope: this.name,
                        collection: collection
                    });
                });
            },
        });
    });
