/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/file-default', 'views/admin/field-manager/fields/link-default', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:fileTypeId', () => {
                this.model.set(this.idName, null);
                this.model.set(this.nameName, null);
            });
        },

        getForeignScope() {
            return 'File';
        },

    });
});