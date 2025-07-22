/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/store/fields/name', 'views/fields/varchar', Dep => {

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'listLink') {
                const link = this.model.get('inStoreLink') || 'https://store.atrocore.com';
                this.$el.html(`<a href="${link}" target="_blank">${this.model.get('name')}</a>`);
            }
        },

    });
});

