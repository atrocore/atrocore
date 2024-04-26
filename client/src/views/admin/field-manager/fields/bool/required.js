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
        hasListenOnce: false,
        afterRender(){
            Dep.prototype.afterRender.call(this)
            let $el = $("input[name='notNull']")
            this.toggleDefault($el.is(':checked'))
            if(!this.hasListenOnce){
                $el.change(e =>{
                    this.hasListenOnce = true
                    this.toggleDefault($(e.currentTarget).is(':checked'))
                })
            }
        },
        toggleDefault(hide) {
            const $default = this.$el.parents('.panel-body').find(`.cell[data-name=${this.name}]`);

            if (hide) {
                $default.addClass('hidden');
            } else {
                $default.removeClass('hidden');
            }

        },
    })
})
