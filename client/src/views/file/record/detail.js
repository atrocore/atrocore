/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        duplicateAction: false,

        sideView: "views/file/record/detail-side",

        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (this.getMetadata().get('app.file.image.extensions').includes(this.model.get('extension'))) {
                this.dropdownItemList.push({
                    'label': 'Open',
                    'name': 'openInTab'
                });
            }
        },

        actionOpenInTab: function () {
            window.open(this.model.get('downloadUrl'), "_blank");
        },

    })
);