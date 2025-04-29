/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/is-duplicatable-relation', 'views/fields/bool', Dep => {

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let shouldHide = true;
            if (
                this.model.get('type') === 'linkMultiple'
                && this.model.get('duplicateIgnore') !== true
                && this.model.get('notStorable') !== true
                && this.model.get('disabled')!== true
                && !(this.getMetadata().get('app.nonDuplicatableRelations') || []).includes(this.model.get('code'))
            ) {
                shouldHide = false;
            }

            if(shouldHide) {
                this.hide();
            }
        }
    });
});
