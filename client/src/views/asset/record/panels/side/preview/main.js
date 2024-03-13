/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/record/panels/side/preview/main', 'view', Dep => {
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

            setup() {
                Dep.prototype.setup.call(this);

                this.listenTo(this.model, "change:fileId", () => {
                    this.reRender();
                });
            },

            isVideo() {
                const extensions = this.getMetadata().get('dam.video.extensions') || [];

                return $.inArray(this.getFileNameExtension(), extensions) !== -1;
            },

            hasVideoPlayer() {
                const extensions = this.getMetadata().get('dam.video.videoPlayerExtensions') || [];

                return $.inArray(this.getFileNameExtension(), extensions) !== -1;
            },

            isImage() {
                const extensions = this.getMetadata().get('fields.asset.hasPreviewExtensions') || [];

                return $.inArray(this.getFileNameExtension(), extensions) !== -1;
            },

            getFileNameExtension() {
                return (this.model.get('fileName') || '').split('.').pop().toLowerCase();
            },

            data() {
                let data = {
                    originPath: (!this.model.get('filePathsData')) ? null : this.model.get('filePathsData').download,
                    thumbnailPath: null,
                    fileId: this.model.get('fileId'),
                    path: this.options.el,
                    hasVideoPlayer: this.hasVideoPlayer() && this.model.get('filePathsData'),
                    isImage: this.isImage(),
                    icon: this.model.get('icon')
                };

                if (this.model.get('filePathsData') && this.model.get('filePathsData').thumbs && this.model.get('filePathsData').thumbs.large) {
                    data.thumbnailPath = this.model.get('filePathsData').thumbs.large;
                }

                if (data.isImage && !data.thumbnailPath && data.fileId) {
                    this.ajaxGetRequest(`Attachment/${data.fileId}`, {}, {async: false}).then(response => {
                        if (response.pathsData) {
                            data.thumbnailPath = response.pathsData.thumbs.large;
                        }
                    });
                }

                if (data.hasVideoPlayer || data.isImage) {
                    data.icon = null;
                }

                return data
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if ((!this.isImage() && !this.isVideo()) || (this.model.get('filesIds') && this.model.get('filesIds').length > 0)) {
                    this.$el.parent().hide();
                } else {
                    this.$el.parent().show();
                }

                if (this.isVideo() && !this.hasVideoPlayer() && this.model.get('filePathsData')) {
                    this.$el.find('.row').append(`<div class="col-sm-12" style="text-align: left; margin-top: 10px"><span style="font-size: 12px">${this.translate('availableVideoFormats', 'labels', 'Asset')}</span></div>`);
                }
            },

        });
    }
);