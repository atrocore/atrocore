/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/fields/read-action', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
            this.listenTo(this.model, 'change:name', () => {
                this.prepareOptionsList();
                this.reRender();
            });
        },

        prepareOptionsList() {
            this.originalOptionList = this.params.options = this.getMetadata().get(`scopes.${this.model.get('name')}.aclActionLevelListMap.read`) || this.getMetadata().get(`entityDefs.RoleScope.fields.readAction.options`);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const aclActionList = this.getMetadata().get(`scopes.${this.model.get('name')}.aclActionList`) || ['read'];

            if (['detail', 'edit'].includes(this.mode) && this.model.get('hasAccess')) {
                this.$el.parent().hide();
                if (aclActionList.includes('read')) {
                    this.$el.parent().show();
                }
            }
        },

    });
});

