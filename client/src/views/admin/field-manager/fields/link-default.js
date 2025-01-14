/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/link-default', 'views/fields/link', Dep => {

    return Dep.extend({

        createDisabled: true,

        setup() {
            this.idName = 'default';
            this.nameName = 'defaultName';

            this.foreignScope = this.getForeignScope();
            this.listenTo(this.model, 'change:foreignEntityId', () => {
                this.foreignScope = this.getForeignScope();
                this.reRender();
            });

            Dep.prototype.setup.call(this);
        },

        getForeignScope() {
            return this.model.get('foreignEntityId');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit' && this.name === 'default') {
                if (!this.getForeignScope()) {
                    this.$el.parent().parent().hide();
                } else {
                    this.$el.parent().parent().show();
                }
            }

        },

    });
});