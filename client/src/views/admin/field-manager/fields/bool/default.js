/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/bool/default', 'views/fields/bool', function (Dep) {

    return Dep.extend({
        hasListenOnce: false,
        setup(){
          this.notNull = this.model.get('notNull') === true
        },
        afterRender(){
            Dep.prototype.afterRender.call(this)
            if(!this.hasListenOnce){
                $("input[name='notNull']").change(e =>{
                    this.hasListenOnce = true
                    this.notNull = $(e.currentTarget).is(':checked')
                    this.reRender()
                })
            }
        }
    })
})
