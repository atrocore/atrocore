/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/disable-attribute-linking', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.initialAttributes = Espo.Utils.cloneDeep(this.model.attributes);
            this.listenTo(this.model, 'after:save',() => {
                this.initialAttributes = Espo.Utils.cloneDeep(this.model.attributes);
            })
            this.listenTo(this.model, 'change:hasClassification', () => {
                if(!this.model.get('hasClassification')) {
                    this.model.set(this.name, false);
                }
                this.reRender();
            });
        },

        afterRender(){
            Dep.prototype.setup.call(this);

            this.hide();

            if(!this.model.isNew()
                && this.initialAttributes['hasAttribute']
                && this.initialAttributes['hasClassification']
                && this.initialAttributes['id'] !== 'Listing'
                && this.model.get('hasClassification')
            ){
                this.show();
            }
        }
    });
});
