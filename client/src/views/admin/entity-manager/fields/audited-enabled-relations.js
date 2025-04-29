/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/admin/entity-manager/fields/audited-enabled-relations', 'views/fields/multi-enum', function (Dep) {

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
            let scope = this.model.get('code');
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
            });

            let newValue = [];
            (this.model.get(this.name) || []).forEach(field => {
                if (this.params.options.includes(field)) {
                    newValue.push(field);
                }
            });
            this.model.set(this.name, newValue);
        },
    })
});
