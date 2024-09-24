/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({
        getConfirmMessage: function (_prev, attrs, model) {
            if (model.isNew() && model.get('isDefault')) {
                return this.translate('profileWillNewDefault', 'messages', 'LayoutProfile')
            }
            return Dep.prototype.getConfirmMessage.call(this, _prev, attrs, model);
        }
    });
});

