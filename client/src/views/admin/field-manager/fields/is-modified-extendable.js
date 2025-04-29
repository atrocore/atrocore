/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/is-modified-extendable', 'views/fields/bool', Dep => {

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let shouldHide = true;
            let scope = this.model.get('entityId');
            let field = this.model.get('code');
            if (
                this.model.get('type') === 'linkMultiple'
                && this.model.get('notStorable') !== true
                && this.model.get('disabled') !== true
                && this.getMetadata().get(['entityDefs', scope, 'links', field, 'relationName'])
            ) {
                let relEntity = this.getMetadata().get(['entityDefs', scope, 'links', field, 'relationName']);
                relEntity = relEntity.charAt(0).toUpperCase() + relEntity.slice(1);

                if ((this.getMetadata().get(['scopes', relEntity, 'type']) || 'Base') === 'Relation') {
                  shouldHide = false;
                }
            }

            if (
                this.model.get('type') === 'linkMultiple'
                && this.model.get('notStorable') !== true
                && this.model.get('disabled') !== true
                && (this.getMetadata().get(['scopes', scope, 'modifiedExtendedLinks']) || []).includes(field)
            ) {
               shouldHide = false;
            }

            if(shouldHide) {
                this.hide();
            }
        }
    });
});
