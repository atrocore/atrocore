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

        prepareLayoutData(data) {
            Dep.prototype.prepareLayoutData.call(this, data);

            if (!this.model.get('primaryEntityId')) {
                return;
            }

            const standardFields = ['code', 'name', 'namePlural', 'type', 'primaryEntity', 'iconClass'];
            const derivativeFields = this.getMetadata().get('app.derivativeEntityFields') || [];
            const allowedFields = new Set([...standardFields, ...derivativeFields]);

            (data.layout || []).forEach(panel => {
                if (!panel.rows) {
                    return;
                }

                panel.rows = panel.rows
                    .map(row => row.map(cell => (cell && allowedFields.has(cell.name)) ? cell : false))
                    .filter(row => row.some(cell => cell !== false));
            });

            data.layout = (data.layout || []).filter(panel => panel.rows && panel.rows.length > 0);
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
