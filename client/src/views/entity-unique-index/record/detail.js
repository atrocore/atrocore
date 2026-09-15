/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-unique-index/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (!this.model.get('isCustom')) {
                this.removeButton('delete');
            }
        }

    });
});
