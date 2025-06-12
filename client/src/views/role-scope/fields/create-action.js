/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/fields/create-action', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const aclActionList = this.getMetadata().get(`scopes.${this.model.get('name')}.aclActionList`) || ['create'];

            if (['detail', 'edit'].includes(this.mode)) {
                this.$el.parent().hide();
                if (aclActionList.includes('create')) {
                    this.$el.parent().show();
                }
            }
        },

    });
});

