/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/associated-record/fields/related-records', 'views/fields/link-multiple',
    Dep => Dep.extend({

        selectBoolFilterList: ['notAssociatedRecords', 'notEntity'],

        getAssociationScope() {
            return this.getMetadata().get(`scopes.${this.model.name}.associatesForEntity`)
        },

        isRequired(){
            return true;
        },

        boolFilterData: {
            notEntity() {
                const scope = this.getAssociationScope()
                return this.model.get(`main${scope}Id`);
            },
            notAssociatedRecords() {
                const scope = this.getAssociationScope()
                return {
                    mainRecordId: this.model.get(`main${scope}Id`),
                    associationId: this.model.get('associationId')
                };
            }
        },

    })
);
