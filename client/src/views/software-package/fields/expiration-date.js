/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/software-package/fields/expiration-date', 'views/fields/date',
    Dep => Dep.extend({
        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (!['list', 'detail'].includes(this.mode)) {
                return;
            }

            const value = this.model.get(this.name);

            if (value === null) {
                const $span = this.$el.find('span');

                $span.removeClass('text-gray');
                $span.html('&infin;');

                return;
            }

            if (value < this.getDateTime().getToday()) {
                this.$el.find('span').css('color', 'red');
            }
        },

    })
);
