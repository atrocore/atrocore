/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/validation-rule/modals/edit', 'views/modals/edit', function (Dep) {

    return Dep.extend({

        fullFormDisabled: true,

        sideDisabled: true,

        actionSave() {
            this.confirm(this.translate('recheckAllAssets', 'confirmations', 'ValidationRule'), () => {
                Dep.prototype.actionSave.call(this);
            });
        },

    });
});

