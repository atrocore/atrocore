/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-followed-record/fields/entity-id', 'views/fields/varchar',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list' || this.mode === 'detail') {
                this.$el.html(`<a href="/#${this.model.get('entityType')}/view/${this.model.get('entityId')}">${this.model.get('entityId')}</a>`);
            }

        },

    })
);
