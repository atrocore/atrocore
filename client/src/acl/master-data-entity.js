/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('acl/master-data-entity', 'acl', Dep => {

    return Dep.extend({

        checkScope(data, action, precise, entityAccessData) {
            if (['create', 'delete'].includes(action)) {
                return false;
            }

            return Dep.prototype.checkScope.call(this, data, action, precise, entityAccessData);
        },

        checkModel(model, data, action, precise) {
            if (['create', 'delete'].includes(action)) {
                return false;
            }

            return Dep.prototype.checkModel.call(this, model, data, action, precise);
        },

    });
});
