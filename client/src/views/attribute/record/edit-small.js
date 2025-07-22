/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/record/edit-small', 'views/record/edit-small',
    Dep => Dep.extend({
        convertDetailLayout(simplifiedLayout) {
            if (this.getRouter().getLast().controller === 'AttributeGroup'
                && !this.hasLayoutField(simplifiedLayout[0].rows, 'sortOrder')) {
                simplifiedLayout[0].rows.push([
                    {
                        name: 'sortOrder'
                    },
                    false
                ]);
            }

            return Dep.prototype.convertDetailLayout.call(this, simplifiedLayout);
        },

        hasLayoutField(layout, field) {
            for (let row of layout.values()) {
                for (let item of row.values()) {
                    if (item !== false && item['name'] === field) {
                        return true;
                    }
                }
            }

            return false;
        }
    })
);
