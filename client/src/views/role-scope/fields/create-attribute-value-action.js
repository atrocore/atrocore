/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/fields/create-attribute-value-action', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name change:hasAccess', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['detail', 'edit'].includes(this.mode)) {
                this.$el.parent().hide();
                this.setNotRequired()
                if (this.getMetadata().get(`scopes.${this.model.get('name')}.hasAttribute`) && this.model.get('hasAccess')) {
                    this.$el.parent().show();
                    this.setRequired()
                }
            }
        },

    });
});

