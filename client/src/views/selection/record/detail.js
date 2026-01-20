
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        entityTypeField: 'entity',

        setup: function() {
            Dep.prototype.setup.call(this);

           this.onModelReady(() => {
               if( this.getAcl().check(this.scope, 'edit')) {
                   let dropdownItems = [];
                   if(this.shouldShowDropdownItem()) {
                       let stagingEntities = this.getStagingEntities(this.model.get(this.entityTypeField))
                       stagingEntities.forEach((e,key) => {
                           dropdownItems.push({
                               action: 'addStagingItem',
                               name: 'addStagingItem'+key,
                               label: this.translate('Add') + ' ' + e,
                               id: e
                           });
                       });
                   }

                   this.additionalButtons.push({
                       action: 'addItem',
                       name: 'addItem',
                       label: this.translate('addItem'),
                       dropdownItems: dropdownItems
                   });
               }
           })
        },

        shouldShowDropdownItem() {
           return this.model.get('type') === 'single'
        },

        getStagingEntities(masterEntity) {
            let result = [];
            _.each(this.getMetadata().get(['scopes']), (scopeDefs, scope) => {
                if(scopeDefs.primaryEntityId === masterEntity) {
                    result.push(scope);
                }
            })
            return result;
        }
    });
});