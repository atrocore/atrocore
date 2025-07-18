/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/associated-record/fields/associate-everything', 'views/fields/bool',
    Dep => Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this)

            this.listenTo(this.model, `change:associationId change:reverseAssociationId change:associatedItemsIds`, () => {
                this.reRender()
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('associationId') &&
                (this.model.get('associationId') === this.model.get('reverseAssociationId')) &&
                (this.model.get('associatedItemsIds') || []).length > 1) {
                this.show()
            } else {
                this.hide()
            }
        }
    })
);
