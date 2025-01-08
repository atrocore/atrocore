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

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            const scope = this.model.get('code') ?? this.model.get('name');

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

            let newValue = [];
            (this.model.get(this.name) || []).forEach(field => {
                if (this.params.options.includes(field)) {
                    newValue.push(field);
                }
            });
            this.model.set(this.name, newValue);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.checkFieldVisibility(this.model.get('relationInheritance'));

            const $relationInheritance = this.$el.parent().parent().parent().find('.field[data-name="relationInheritance"] input');
            $relationInheritance.change(() => {
                this.checkFieldVisibility($relationInheritance.is(':checked'));
            });
        },

        checkFieldVisibility(relationInheritance) {
            if (this.model.get('type') === 'Hierarchy' && relationInheritance === true) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

    });
});