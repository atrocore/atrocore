/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('acl/entity', 'acl', Dep => {

    return Dep.extend({

        checkModel(model, data, action, precise) {
            if (model.get('customizable') === false) {
                const list = model.get('onlyEditableEmFields')

                if (!list || list.length === 0) {
                    return false;
                }
            }

            return Dep.prototype.checkModel.call(this, model, data, action, precise);
        },

    });
});
