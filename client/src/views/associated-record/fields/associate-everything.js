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

        getAssociationScope() {
            return this.getMetadata().get(`scopes.${this.model.name}.associatesForEntity`)
        },

        setup: function () {
            Dep.prototype.setup.call(this)
            const scope = this.getAssociationScope()

            this.listenTo(this.model, `change:associationId change:backwardAssociationId change:related${scope}sIds`, () => {
                this.reRender()
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            const scope = this.getAssociationScope()

            if (this.model.get('associationId') &&
                (this.model.get('associationId') === this.model.get('backwardAssociationId')) &&
                (this.model.get(`related${scope}sIds`) || []).length > 1) {
                this.show()
            } else {
                this.hide()
            }
        }
    })
);
