/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('acl/matched-record', 'acl', Dep => {

    return Dep.extend({

        checkScope(data, action, precise, entityAccessData) {
            if (action !== 'read') {
                return false;
            }

            return Dep.prototype.checkScope.call(this, data, action, precise, entityAccessData);
        },

        checkModel(model, data, action, precise) {
            if (action !== 'read') {
                return false;
            }

            return Dep.prototype.checkModel.call(this, model, data, action, precise);
        },

    });
});
