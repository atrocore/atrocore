/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/fields/stream-action', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const scope = this.model.get('name');

            const aclActionList = this.getMetadata().get(`scopes.${scope}.aclActionList`) || ['stream'];

            this.$el.parent().hide();
            if (aclActionList.includes('stream') && !this.getMetadata().get(`scopes.${scope}.streamDisabled`)) {
                this.$el.parent().show();
            }
        },

    });
});

