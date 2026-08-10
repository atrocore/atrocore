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

            if (this.model.get(this.name) === null && ['list', 'detail'].includes(this.mode)) {
                const $span = this.$el.find('span');

                $span.removeClass('text-gray');
                $span.html('&infin;');
            }
        },

    })
);
