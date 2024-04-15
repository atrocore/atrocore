/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/bool/required', 'views/fields/bool', function (Dep) {

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.toggleField(this.model.get('disableNullValue'));

            this.listenTo(this.model, 'change:disableNullValue', () => {
                this.toggleField(this.model.get('disableNullValue'));
            });
        },

        toggleField(hide) {
            const $default = this.$el.parents('.panel-body').find('.cell[data-name=required]');

            if (hide) {
                $default.addClass('hidden');
            } else {
                this.model.set('required', false)
                $default.removeClass('hidden');
            }
        },

    });

});
