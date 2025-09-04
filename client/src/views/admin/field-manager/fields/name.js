/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/name', 'views/fields/varchar-with-translation-sign', Dep => {

    return Dep.extend({

        getEntityScope() {
            return this.model.get('entityId');
        },

        getEntityFieldName() {
            return this.model.get('code');
        },

    });
});
