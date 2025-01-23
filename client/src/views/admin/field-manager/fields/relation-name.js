/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/relation-name', 'views/fields/varchar', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityId change:foreignEntityId', () => {
                if (this.model.get('entityId') && this.model.get('foreignEntityId')) {
                    this.model.set(this.name, this.model.get('entityId') + this.model.get('foreignEntityId'));
                }
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' && this.model.get(this.name)){
                this.$el.html(`<a href="/#Entity/view/${this.model.get(this.name)}">${this.model.get(this.name)}</a>`)
            }
        },

    });
});
