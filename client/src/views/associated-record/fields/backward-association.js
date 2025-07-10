/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/associated-record/fields/backward-association', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive', 'notUsedAssociations','onlyForEntity'],

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

    })
);
