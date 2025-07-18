/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/associated-record/fields/reverse-association', 'views/fields/link',
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
                    mainRecordId: this.model.get('associatingItemId'),
                    relatedRecordId: this.model.get('associatedItemId')
                };
            },
            onlyForEntity() {
                return this.getAssociationScope();
            }
        },

    })
);
