/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/fields/preview', 'view',
    Dep => Dep.extend({

        template: "asset/fields/preview/list",

        events: {
            'click a[data-action="showImagePreview"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                let id = $(e.currentTarget).data('id');
                this.createView('preview', 'views/modals/image-preview', {
                    id: id,
                    model: this.model,
                    type: "asset"
                }, function (view) {
                    view.render();
                });
            }
        },

        data() {
            return {
                "originPath": (this.model.get('filePathsData')) ? this.model.get('filePathsData').download : null,
                "thumbnailPath": (this.model.get('filePathsData')) ? this.model.get('filePathsData').thumbs.small : null,
                "timestamp": this.getTimestamp(),
                "fileId": this.model.get('fileId'),
                "icon": this.model.get('icon')
            };
        },

        getTimestamp() {
            return (Math.random() * 10000000000).toFixed();
        },

    })
);