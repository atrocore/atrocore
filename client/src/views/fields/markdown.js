/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/markdown', ['views/fields/text', 'lib!EasyMDE'], function (Dep, EasyMDE) {
    return Dep.extend({

        listTemplate: 'fields/markdown/list',

        detailTemplate: 'fields/markdown/detail',

        editor: null,

        minHeight: 200,

        maxHeight: 400,

        getMaxUploadSize: function () {
            return (this.getConfig().get('chunkFileSize') || 2) * 1024 * 1024;
        },

        setup() {
            Dep.prototype.setup.call(this);

            if (this.params.maxHeight < this.params.minHeight) {
                this.params.minHeight = this.params.maxHeight;
            }

            this.minHeight = this.params.minHeight || this.minHeight;
            this.maxHeight = this.params.maxHeight || this.maxHeight;
        },

        getToolbarItems() {
            const items = [
                'undo', 'redo', '|',
                'heading-1', 'heading-2', 'heading-3', '|',
                'bold', 'italic', 'strikethrough', '|',
                'unordered-list', 'ordered-list', 'code', 'quote', 'link', 'horizontal-rule', '|',
            ];

            if (this.getAcl().check('File', 'read')) {
                items.push({
                    name: "selectImage",
                    action: (editor) => {
                        this.notify('Loading...');
                        this.createView('selectFileDialog', this.getMetadata().get('clientDefs.File.modalViews.select'), {
                            scope: 'File',
                            filters: {
                                fileType: {
                                    type: 'in',
                                    field: 'typeId',
                                    attribute: 'typeId',
                                    value: ['a_image', 'a_favicon']
                                }
                            },
                        }, view => {
                            view.render();
                            this.notify(false);
                            this.listenTo(view, 'select', model => {
                                const file = new File([], model.get('name'));
                                file.url = model.get('downloadUrl');
                                this.editor.uploadImageUsingCustomFunction(this.uploadImage.bind(this), file)
                            });
                        });
                    },
                    className: "fa fa-file-image-o",
                    title: "Select"
                });
            }

            if (this.getAcl().check('File', 'create')) {
                items.push({
                    name: "uploadImage",
                    action: (editor) => {
                        this.notify('Loading...')
                        this.createView('upload', 'views/file/modals/upload', {
                            scope: 'File',
                            fullFormDisabled: true,
                            layoutName: 'upload',
                            multiUpload: false,
                            attributes: _.extend(this.model.attributes, {share: true}),
                        }, view => {
                            view.render();
                            this.notify(false);
                            this.listenTo(view.model, 'after:file-upload', entity => {
                                const file = new File([], entity.name);
                                file.url = entity.sharedUrl;
                                this.editor.uploadImageUsingCustomFunction(this.uploadImage.bind(this), file)
                            });
                            this.listenToOnce(view, 'close', () => {
                                this.clearView('upload');
                            });
                        });
                    },
                    className: "fa fa-download",
                    title: "Upload from a local filesystem"
                });
            }

            if (items[items.length - 1] !== '|') {
                items.push('|');
            }

            items.push('preview', 'guide');

            return items;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            const element = this.$element.get(0);

            if (this.mode === 'edit' && element && !this.readOnly) {
                this.editor = new EasyMDE({
                    element: element,
                    minHeight: `${this.minHeight}px`,
                    forceSync: true,
                    status: false,
                    initialValue: this.default,
                    sideBySideFullscreen: false,
                    shortcuts: {
                        toggleFullScreen: null,
                        drawImage: null,
                        toggleSideBySide: null
                    },
                    previewRender: (plainText) => marked(plainText),
                    previewClass: ['editor-preview', 'complex-text'],
                    toolbar: this.getToolbarItems(),
                    uploadImage: !!this.getAcl().check('File', 'create'),
                    imageUploadFunction: (file, onSuccess, onError) => {
                        if (!this.getAcl().check('File', 'create')) {
                            Espo.ui.error('You are not allowed to upload images');
                            return;
                        }

                        if (file.size >= this.getMaxUploadSize()) {
                            Espo.ui.notify(`Your file exceeded size limit of ${this.getMaxUploadSize()} MB`);
                            return;
                        }

                        const extensions = this.getMetadata().get(['app', 'file', 'image', 'extensions']);
                        if (!file) {
                            return;
                        }

                        let isImage = false;
                        for (const ext of extensions) {
                            if (file.name.endsWith(ext)) {
                                isImage = true;
                                break;
                            }
                        }

                        if (!isImage) {
                            Espo.ui.error('Your file is not an image.');
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = e => {
                            $.ajax({
                                type: 'POST',
                                url: 'File?silent=true',
                                contentType: "application/json",
                                data: JSON.stringify({
                                    name: file.name,
                                    fileSize: file.size,
                                    fileContents: e.target.result,
                                    share: true
                                }),
                            }).done(response => {
                                file.url = response.sharedUrl;
                                this.uploadImage(file, onSuccess, onError);
                            }).error(response => {
                                Espo.ui.error('Error while uploading file');
                            })
                        };
                        reader.readAsDataURL(file);
                    }
                });

                const scroller = this.editor.codemirror.getScrollerElement();
                if (scroller) {
                    scroller.style.maxHeight = `${this.maxHeight}px`;
                }
            }
        },

        uploadImage(file, onSuccess, onError) {
            if (file.url) {
                onSuccess(file.url);
            } else {
                Espo.ui.error('Cannot upload your image')
            }
        }
    });
});
