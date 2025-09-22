/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('acl/thumbnail-type', 'acl', Dep => {

    return Dep.extend({

        checkModel(model, data, action, precise) {
            if (action === 'delete' && model.get('deleteDisabled')) {
                return false
            }

            return Dep.prototype.checkModel.call(this, model, data, action, precise);
        },

    });
});
