/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/record/panels/attributes', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:hasAccess', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.model.get('hasAccess') && this.getMetadata().get(`scopes.${this.model.get('name')}.hasAttribute`)) {
                this.$el.parent().show();
            }
        },

    });
});

