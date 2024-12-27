/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/file-types', 'views/fields/link-multiple', Dep => {

    return Dep.extend({

        setup() {
            this.idsName = this.name;
            this.nameHashName = this.name + 'Names';
            this.foreignScope = 'FileType';

            Dep.prototype.setup.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['detail', 'edit'].includes(this.mode)) {
                this.$el.parent().hide();

                let foreignEntity = this.getMetadata()
                    .get(['entityDefs', this.model.get('entityId'), 'links', this.model.get('code'), 'entity']);

                if (foreignEntity === 'File') {
                    this.$el.parent().show();
                }
            }
        },

    });
});