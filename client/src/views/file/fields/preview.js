/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/preview', 'view',
    Dep => Dep.extend({

        template: "file/fields/preview/list",

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
                "timestamp": this.getTimestamp(),
                "originPath": this.model.get('downloadUrl'),
                "thumbnailPath": this.model.get('smallThumbnailUrl'),
                "id": this.model.get('id'),
                "hasIcon": !(this.getMetadata().get('app.file.image.hasPreviewExtensions') || []).includes(this.model.get('extension')),
                "extension": this.model.get('extension')
            };
        },

        getTimestamp() {
            return (Math.random() * 10000000000).toFixed();
        },

    })
);