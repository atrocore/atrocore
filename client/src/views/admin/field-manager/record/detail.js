/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setup: function () {
            if (!this.getMetadata().get(`entityDefs.${this.model.get('entityId')}.fields.${this.model.get('code')}.isCustom`)) {
                this.buttonList.push({
                    name: "resetToDefault",
                    label: this.translate("resetToDefault", "labels", "EntityField"),
                    action: "resetToDefault"
                });
            }

            Dep.prototype.setup.call(this);
        },

        actionResetToDefault: function () {
            this.confirm(this.translate('resetToDefault', 'confirmations', 'EntityField'), () => {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest('EntityField/action/resetToDefault', {
                    scope: this.model.get('entityId'),
                    field: this.model.get('code')
                }).then(() => {
                    this.model.fetch().then(() => {
                        this.notify('Done', 'success');
                    });
                });
            });
        }
    });
});
