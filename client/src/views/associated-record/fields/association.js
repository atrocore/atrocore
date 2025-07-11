/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/associated-record/fields/association', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyActive', 'notUsedAssociations', 'onlyForEntity'],

        getAssociationScope() {
            return this.getMetadata().get(`scopes.${this.model.name}.associatesForEntity`)
        },

        boolFilterData: {
            notUsedAssociations() {
                const scope = this.getAssociationScope()
                return {
                    scope: scope,
                    mainRecordId: this.model.get(`main${scope}Id`),
                    relatedRecordId: this.model.get(`related${scope}Id`)
                };
            },
            onlyForEntity() {
                return this.getAssociationScope();
            }
        },

        select(model) {
            Dep.prototype.select.call(this, model);

            if (model.get('backwardAssociationId') && !this.model.get('backwardAssociationId')) {
                this.model.set({
                    backwardAssociationId: model.get('backwardAssociationId'),
                    backwardAssociationName: model.get('backwardAssociationName')
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (!this.model.id && !!this.getRecordView() && this.mode === 'edit') {
                // fetch default
                const data = {
                    where: [
                        {
                            type: 'isTrue',
                            attribute: 'default'
                        },
                        {
                            type: 'equals',
                            attribute: 'entityId',
                            value: this.getAssociationScope()
                        }
                    ]
                }
                this.ajaxGetRequest('Association', data).then(data => {
                    const association = data.list[0];
                    if (association) {
                        this.model.set({
                            associationId: association.id,
                            associationName: association.name,
                            backwardAssociationId: association.backwardAssociationId,
                            backwardAssociationName: association.backwardAssociationName
                        })
                    }
                });
            }
        }
    })
);
