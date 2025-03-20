/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/modified-extended-relations', 'views/fields/multi-enum', function (Dep) {
    return Dep.extend({
        setupOptions() {
            const scope = this.model.get('code') ?? this.model.get('name');

            this.params.options = [];
            this.translatedOptions = {};

            if (scope === 'Product' && this.getMetadata().get('scopes.Product.module') === 'Pim') {
                this.params.options.push('productAttributeValues');
                this.translatedOptions['productAttributeValues'] = this.translate('productAttributeValues', 'fields', scope);
            }

            $.each((this.getMetadata().get(['entityDefs', scope, 'fields']) || {}), (field, fieldDefs) => {
                if (
                    fieldDefs.type === 'linkMultiple'
                    && fieldDefs.notStorable !== true
                    && fieldDefs.disabled !== true
                    && this.getMetadata().get(['entityDefs', scope, 'links', field, 'relationName'])
                ) {
                    let relEntity = this.getMetadata().get(['entityDefs', scope, 'links', field, 'relationName']);
                    relEntity = relEntity.charAt(0).toUpperCase() + relEntity.slice(1);

                    if ((this.getMetadata().get(['scopes', relEntity, 'type']) || 'Base') === 'Relation') {
                        this.params.options.push(field);
                        this.translatedOptions[field] = this.translate(field, 'fields', scope);
                    }
                }

                if (
                    fieldDefs.type === 'linkMultiple'
                    && fieldDefs.notStorable !== true
                    && fieldDefs.disabled !== true
                    && (this.getMetadata().get(['scopes', scope, 'modifiedExtendedLinks']) || []).includes(field)
                ) {
                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', scope);
                }
            });

            let newValue = [];
            (this.model.get(this.name) || []).forEach(field => {
                if (this.params.options.includes(field)) {
                    newValue.push(field);
                }
            });
            this.model.set(this.name, newValue);
        }
    })
});
