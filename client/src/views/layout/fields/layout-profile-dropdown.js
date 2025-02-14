/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/layout-profile-dropdown', 'views/fields/link-dropdown', function (Dep) {

    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this)

            if (!this.model.get(this.idName) && this.params.options.length) {
                this.model.set(this.idName, this.params.options[0])
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this)

            this.$el.find('select').css('background-color', '#fff');
        }

    });
});

