/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/foreign-code', 'views/fields/varchar', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityId change:type change:relationType', () => {
                if (this.model.get('entityId')) {
                    if (this.model.get('type') === 'link') {
                        this.model.set(this.name, this.lcfirst(this.model.get('entityId')) + 's');
                    }

                    if (this.model.get('type') === 'linkMultiple') {
                        if (this.model.get('relationType') === 'oneToMany') {
                            this.model.set(this.name, this.lcfirst(this.model.get('entityId')));
                        } else {
                            this.model.set(this.name, this.lcfirst(this.model.get('entityId')) + 's');
                        }
                    }
                }
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' && this.model.get(this.name)) {
                this.$el.html(`<a href="/#EntityField/view/${this.model.get('foreignEntityId')}_${this.model.get(this.name)}">${this.model.get(this.name)}</a>`);
            }
        },

        lcfirst(str) {
            if (!str) {
                return str;
            }
            return str.charAt(0).toLowerCase() + str.slice(1);
        }

    });
});
