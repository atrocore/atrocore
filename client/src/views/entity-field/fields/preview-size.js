/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/preview-size', 'views/fields/enum', Dep => {
    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {};
            $.each((this.getMetadata().get(`app.thumbnailTypes`) || {}), (type, data) => {
                this.translatedOptions[type] = this.translate(type, 'thumbnailTypes')
            })
            this.params.options = Object.keys(this.translatedOptions);
        },

    });

});
