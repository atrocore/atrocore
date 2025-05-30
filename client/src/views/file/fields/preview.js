/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/preview', 'views/fields/file',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.idName = 'id';
            this.nameName = 'name';

            this.mode = 'list';
            this.previewSize = 'large';

            this.listenTo(this.model, 'reuploaded', () => {
                this.model.fetch().then(() => this.reRender());
            });
        },

        getFilePathsData: function (model) {
            if (!model) {
                model = this.model;
            }

            return {
                download: model.get('downloadUrl'),
                thumbnails: {
                    small: model.get('smallThumbnailUrl'),
                    medium: model.get('mediumThumbnailUrl'),
                    large: model.get('largeThumbnailUrl'),
                }
            };
        },

        hasImagePreview() {
            return (this.getMetadata().get('app.file.image.hasPreviewExtensions') || []).includes(this.model.get('extension'));
        },

        hasVideoPlayer: function () {
            return (this.getMetadata().get('app.file.video.hasVideoPlayerExtensions') || []).includes(this.model.get('extension'));
        },

        getValueForDisplay: function () {
            let id = this.model.get(this.idName);
            if (!id) {
                return false;
            }
            if (this.mode === 'list') {

                if (this.hasImagePreview() && this.getImageUrl(id, this.previewSize)) {
                    return '<div class="attachment-preview"><a data-action="showImagePreview" data-id="' + id + '" href="' + this.getImageUrl(id) + '"><img src="' + this.getImageUrl(id, this.previewSize) + '?time=' + (new Date(this.model.get('fileMtime'))).getTime()  + '" class="image-preview"></a></div>';
                } else {
                    return '<a' + (this.hasVideoPlayer() ? ' data-action="showVideoPreview"' : '') + ' href="' + this.getDownloadUrl(id) + '"><span class="fiv-cla fiv-icon-' + this.model.get('extension') + ' fiv-size-lg"></span></a>';
                }
            }else{
                if (this.hasImagePreview() && this.getImageUrl(id, this.previewSize)) {
                    return '<div class="attachment-preview"><a data-action="showImagePreview" data-id="' + id + '" href="' + this.getImageUrl(id) + '"><img src="' + this.getImageUrl(id, this.previewSize) + '?time=' + (new Date(this.model.get('fileMtime'))).getTime() + '" class="image-preview"></a></div>';
                } else if (this.hasVideoPlayer()) {
                    return '<video src="' + this.getDownloadUrl(id) + '" controls width="100%"></video>';
                } else {
                    return '<a href="' + this.getDownloadUrl(id) + '" target="_blank"><span class="fiv-cla fiv-icon-' + this.model.get('extension') + ' fiv-size-lg"></span></a>';
                }
            }
        }
    })
);