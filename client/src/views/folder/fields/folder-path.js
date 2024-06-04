/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/folder/fields/folder-path', 'views/fields/varchar',
    Dep => Dep.extend({

        data() {
            return {};
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const pathData = this.model.get(this.name) || [];

            const path = [];
            if (pathData.length > 0) {
                if (this.model.name === 'File') {
                    path.unshift(`<a href="/#Folder/view/${pathData[0].id}">${pathData[0].name}</a>`);
                }
                pathData.forEach(item => {
                    path.unshift(`<a href="/#Folder/view/${item.parentId}">${item.parentName}</a>`);
                });
                this.$el.html(path.join(' / '));
            } else {
                this.$el.html('<span class="text-gray">Null</span>');
            }

        },

    })
);
