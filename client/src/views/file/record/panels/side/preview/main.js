/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/preview/main', 'view', Dep => {
        return Dep.extend({

            template: "asset/record/panels/side/preview/main",

            events: {
                'click a[data-action="showImagePreview"]': function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    let id = $(e.currentTarget).data('id');
                    this.createView('preview', 'views/modals/image-preview', {
                        id: id,
                        model: this.model
                    }, function (view) {
                        view.render();
                    });
                }
            },

            isVideo() {
                return this.getMetadata().get('app.file.video.extensions').includes(this.model.get('extension'));
            },

            hasVideoPlayer() {
                return this.getMetadata().get('app.file.video.videoPlayerExtensions').includes(this.model.get('extension'));
            },

            isImage() {
                return this.getMetadata().get('app.file.image.extensions').includes(this.model.get('extension'));
            },

            data() {
                return {
                    originPath: this.model.get('downloadUrl'),
                    thumbnailPath: this.model.get('largeThumbnailUrl'),
                    fileId: this.model.get('id'),
                    path: this.options.el,
                    hasVideoPlayer: this.hasVideoPlayer(),
                    isImage: this.isImage(),
                    hasIcon: !this.isImage() && !this.hasVideoPlayer(),
                    extension: this.model.get('extension')
                }
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (!this.isImage() && !this.isVideo()) {
                    this.$el.parent().hide();
                } else {
                    this.$el.parent().show();
                }

                if (this.isVideo() && !this.hasVideoPlayer()) {
                    this.$el.find('.row').append(`<div class="col-sm-12" style="text-align: left; margin-top: 10px"><span style="font-size: 12px">${this.translate('availableVideoFormats', 'labels', 'Asset')}</span></div>`);
                }
            },

        });
    }
);