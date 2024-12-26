/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/link-multiple-default', 'views/fields/link-multiple', Dep => {

    return Dep.extend({

        createDisabled: true,

        setup() {
            this.idsName = 'default';
            this.nameHashName = 'defaultNames';
            this.foreignScope = this.getForeignScope();

            Dep.prototype.setup.call(this);
        },

        getForeignScope() {
            return this.getMetadata().get(`entityDefs.${this.model.get('entityId')}.links.${this.model.get('code')}.entity`);
        },

    });
});