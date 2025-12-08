/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/link-attributes-with-classification-automatically', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.initialAttributes = Espo.Utils.cloneDeep(this.model.attributes);
            this.listenTo(this.model, 'after:save', () => {
                this.initialAttributes = Espo.Utils.cloneDeep(this.model.attributes);
            })
            this.listenTo(this.model, 'change:disableAttributeLinking change:hasClassification change:hasAttribute', () => {
                if (!this.isVisible() && this.model.get(this.name)) {
                    this.model.set(this.name, false);
                }
                this.reRender();
            });
        },

        isVisible() {
            return !this.model.isNew()
                && this.initialAttributes['hasAttribute']
                && this.initialAttributes['hasClassification']
                && !this.model.get('disableAttributeLinking')
                && this.model.id !== 'Listing'
                && this.model.get('hasClassification')
                && this.model.get('type') !== 'Derivative'
                && this.model.get('hasAttribute');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.isVisible()) {
                this.show();
            } else {
                this.hide();
            }
        }
    });
});
