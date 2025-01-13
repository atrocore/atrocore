/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/un-inherited-relations', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type change:relationInheritance', () => {
                this.reRender();
            });
        },

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            const scope = this.model.get('code');

            $.each((this.getMetadata().get(['entityDefs', scope, 'fields']) || {}), (field, fieldDefs) => {
                if (
                    fieldDefs.type === 'linkMultiple'
                    && !(this.getMetadata().get('app.nonInheritedRelations') || []).includes(field)
                    && !(this.getMetadata().get(['scopes', scope, 'mandatoryUnInheritedRelations']) || []).includes(field)
                ) {
                    let foreignEntity = this.getMetadata().get(['entityDefs', scope, 'links', field, 'entity']);
                    let isRelationshipEntity = this.getMetadata().get(['scopes', foreignEntity, 'type']) === 'Relationship';
                    if (isRelationshipEntity || this.getMetadata().get(['entityDefs', scope, 'links', field, 'relationName']) || (this.getMetadata().get(['scopes', scope, 'inheritedRelations']) || []).includes(field)) {
                        this.params.options.push(field);
                        this.translatedOptions[field] = this.translate(field, 'fields', scope);
                    }
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('relationInheritance')) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

    });
});