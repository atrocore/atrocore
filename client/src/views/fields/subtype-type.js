/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/subtype-type', 'views/fields/enum',
    Dep => Dep.extend({

        // each option is a subtype of the entity, so the option is labeled with the name of the subtype
        setupTranslation() {
            this.translatedOptions = {};
            (this.params.options || []).forEach(option => {
                const subtype = this.model.name + 'Type' + option.charAt(0).toUpperCase() + option.slice(1);
                this.translatedOptions[option] = this.translate(subtype, 'scopeNames');
            });
        },

    })
);
