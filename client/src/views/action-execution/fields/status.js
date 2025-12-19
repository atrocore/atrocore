/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action-execution/fields/status', 'views/fields/colored-enum',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list' && this.model.get('statusMessage') && this.model.get('statusMessage').length > 0) {
                this.$el.find('.colored-enum').attr('title', this.model.get('statusMessage'));
            }
        },

    })
);