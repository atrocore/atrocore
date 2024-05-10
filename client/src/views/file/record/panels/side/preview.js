/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/preview', 'views/file/fields/preview',
    Dep => Dep.extend({

        hasVideoPlayer() {
            return (this.getMetadata().get('app.file.video.hasVideoPlayerExtensions') || []).includes(this.model.get('extension'));
        },

        getValueForDisplay: function () {
            let id = this.model.get(this.idName);
            if (!id) {
                return false;
            }

            if (this.hasImagePreview() && this.getImageUrl(id, this.previewSize)) {
                return '<div class="attachment-preview"><a data-action="showImagePreview" data-id="' + id + '" href="' + this.getImageUrl(id) + '"><img src="' + this.getImageUrl(id, this.previewSize) + '" class="image-preview"></a></div>';
            } else if (this.hasVideoPlayer()) {
                return '<video src="' + this.getDownloadUrl(id) + '" controls width="100%"></video>';
            } else {
                return '<a href="' + this.getDownloadUrl(id) + '" target="_blank"><span class="fiv-cla fiv-icon-' + this.model.get('extension') + ' fiv-size-lg"></span></a>';
            }
        },

    })
);