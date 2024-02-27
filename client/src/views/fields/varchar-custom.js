/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/varchar-custom', 'views/fields/varchar', function (Dep) {
    return Dep.extend({

        editTemplate: "fields/varchar-custom/edit",

        data() {
            return _.extend({
                readOnly: this.readOnly
            }, Dep.prototype.data.call(this));
        }

    });
});