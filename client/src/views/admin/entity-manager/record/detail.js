/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setup: function () {

            Dep.prototype.setup.call(this);

            if (!this.getMetadata().get(`scopes.${this.model.get('code')}.isCustom`)) {
                this.removeButton('delete');
                this.buttonList.push({
                    name: "resetToDefault",
                    label: this.translate("resetToDefault", "labels", "Entity"),
                    action: "resetToDefault"
                });
            }
        },

        actionResetToDefault: function () {
            this.confirm(this.translate('resetToDefault', 'confirmations', 'Entity'), () => {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest('Entity/action/resetToDefault', {scope: this.model.get('code')}).then(() => {
                    this.model.fetch().then(() => {
                        this.notify('Done', 'success');
                    });
                });
            });
        }
    });
});
