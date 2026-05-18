/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('acl/matching-rule', 'acl', function (Dep) {

    return Dep.extend({

        checkScope: function (data, action, precise) {
            if (this.getUser().isAdmin()) {
                return true;
            }
            return this.aclManager.checkScope('Matching', action, precise);
        },

        checkModel: function (model, data, action, precise) {
            return model.get('editable');
        },

    });
});
