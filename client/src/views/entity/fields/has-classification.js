/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/has-classification', 'views/fields/bool',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.checkVisibility();
            this.listenTo(this.model, 'change:hasAttribute', () => {
                this.checkVisibility();
            });
        },

        checkVisibility() {
            const scope = this.model.id;

            this.$el.parent().hide();
            if (
                !this.model.isNew()
                && !this.getMetadata().get(`scopes.${scope}.attributesDisabled`)
                && this.getMetadata().get(`scopes.${scope}.customizable`)
                && this.model.get('hasAttribute') && scope !== 'Listing'
            ) {
                this.$el.parent().show();
            }
        }

    })
);

