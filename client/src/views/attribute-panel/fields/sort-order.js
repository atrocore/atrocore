/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute-panel/fields/sort-order', 'views/fields/float',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.isNew()) {
                let max = 0;
                $.each((this.getConfig().get('referenceData')?.AttributePanel || {}), (code, row) => {
                    if (row.sortOrder && row.sortOrder > max) {
                        max = row.sortOrder + 10;
                    }
                })

                this.model.set('sortOrder', max);
            }
        },

    })
);
