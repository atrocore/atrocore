/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/user/fields/name', 'views/fields/varchar', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.isNew()) {
                this.listenTo(this.model, 'change:firstName change:lastName', () => {
                    this.generateDisplayName();
                });
            }
        },

        generateDisplayName() {
            let firstName = this.model.get('firstName') || '';
            let lastName = this.model.get('lastName') || '';
            let name = firstName.trim() + ' ' + lastName.trim();
            this.model.set('name', name.trim());
        },

    });

});
