/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset-type/fields/types-to-exclude', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.params.options = [];
            this.translatedOptions = {};

            (this.getMetadata().get('entityDefs.Asset.fields.type.options') || []).forEach(option => {
                if (option !== this.model.get('name')) {
                    this.params.options.push(option);
                    this.translatedOptions[option] = option;
                }
            });
        },
    })
);