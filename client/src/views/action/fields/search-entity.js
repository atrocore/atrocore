/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/search-entity', 'views/fields/entity-type',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                // the records created for the found records are configured via script only
                this.listenTo(this.model, 'change:searchEntity', () => {
                    if (this.model.get('searchEntity')) {
                        this.model.set('updateType', 'script');
                    }
                });
            },

        });
    });
