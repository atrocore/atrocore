/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/is-multilang', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['detail', 'edit'].includes(this.mode)) {
                this.$el.parent().hide();
                if (this.getMetadata().get(`fields.${this.model.get('type')}.multilingual`)) {
                    this.$el.parent().show();
                }
            }
        },

    });
});
