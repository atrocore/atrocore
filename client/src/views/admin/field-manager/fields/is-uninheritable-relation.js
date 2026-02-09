/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/is-uninheritable-relation', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let scope = this.model.get('entityId');
            let field = this.model.get('code');
            let shouldHide = true;

            if (
                this.model.get('type') === 'linkMultiple'
                && !(this.getMetadata().get('app.nonInheritedRelations') || []).includes(field)
                && !(this.getMetadata().get(['scopes', scope, 'mandatoryUnInheritedRelations']) || []).includes(field)
                && this.getMetadata().get(['scopes', scope, 'type']) === 'Hierarchy'
            ) {
                let foreignEntity = this.getMetadata().get(['entityDefs', scope, 'links', field, 'entity']);
                let isRelationshipEntity = this.getMetadata().get(['scopes', foreignEntity, 'type']) === 'Relation';
                if (isRelationshipEntity || this.getMetadata().get(['entityDefs', scope, 'links', field, 'relationName']) || (this.getMetadata().get(['scopes', scope, 'inheritedRelations']) || []).includes(field)) {
                    shouldHide = false;
                }
            }

            if (shouldHide) {
                this.hide();
            }
        }
    });
});
