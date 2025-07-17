/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/fields/required', 'views/fields/bool', function (Dep) {

    return Dep.extend({
        setup(){
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:notNull', () => {
                this.toggleDefault(this.model.get('notNull'))
            })
        },
        afterRender(){
            Dep.prototype.afterRender.call(this)
            this.toggleDefault(this.model.get('notNull'))

        },
        toggleDefault(hide) {
            if (hide) {
               this.hide()
            } else {
               this.show()
            }

        },
    })
})
