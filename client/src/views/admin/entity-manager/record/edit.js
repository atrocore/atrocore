/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/record/edit', 'views/record/edit', Dep => {

    return Dep.extend({
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
