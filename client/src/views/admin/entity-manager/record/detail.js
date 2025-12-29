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

        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (!this.model.get('isCustom')) {
                this.removeButton('delete');
                this.buttonList.push({
                    name: "resetToDefault",
                    label: this.translate("resetToDefault", "labels", "Entity"),
                    action: "resetToDefault"
                });
            }

            if (this.model.get('primaryEntityId')) {
                this.removeButton('delete');
                this.removeButton('duplicate');
            }
        },

        actionResetToDefault: function () {
            this.confirm(this.translate('resetToDefault', 'confirmations', 'Entity'), () => {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest('Entity/action/resetToDefault', { scope: this.model.get('code') }).then(() => {
                    this.model.fetch().then(() => {
                        this.notify('Done', 'success');
                    });
                });
            });
        },

        setupFieldLevelSecurity: function () {
            const list = this.getMetadata().get('scopes.' + this.model.id + '.onlyEditableEmFields')

            if (Array.isArray(list) && list.length > 0) {
                let fieldsList = Object.keys(this.getMetadata().get('entityDefs.Entity.fields') || {})
                fieldsList.forEach((field) => {
                    if (!list.includes(field)) {
                        this.setFieldReadOnly(field, true);
                    }
                });
            }

            Dep.prototype.setupFieldLevelSecurity.call(this)
        }
    });
});
