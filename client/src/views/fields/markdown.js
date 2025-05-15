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
                {
                    name: 'undo',
                    className: 'ph ph-arrow-counter-clockwise',
                    action: EasyMDE.undo,
                    title: 'Undo',
                },
                {
                    name: 'redo',
                    className: 'ph ph-arrow-clockwise',
                    action: EasyMDE.redo,
                    title: 'Redo',
                }, '|',
                {
                    name: 'heading-1',
                    className: 'ph ph-text-h-one',
                    action: EasyMDE.toggleHeading1,
                    title: 'Big Heading'
                },
                {
                    name: 'heading-2',
                    className: 'ph ph-text-h-two',
                    action: EasyMDE.toggleHeading2,
                    title: 'Medium Heading'
                },
                {
                    name: 'heading-3',
                    className: 'ph ph-text-h-three',
                    action: EasyMDE.toggleHeading3,
                    title: 'Small Heading'
                },
                '|',
                {
                    name: 'bold',
                    className: 'ph ph-text-b',
                    action: EasyMDE.toggleBold,
                    title: 'Bold'
                },
                {
                    name: 'italic',
                    className: 'ph ph-text-italic',
                    action: EasyMDE.toggleItalic,
                    title: 'Italic'
                },
                {
                    name: 'strikethrough',
                    className: 'ph ph-text-strikethrough',
                    action: EasyMDE.toggleStrikethrough,
                    title: 'Strikethrough'
                },
                '|',
                {
                    name: 'unordered-list',
                    className: 'ph ph-list-bullets',
                    action: EasyMDE.toggleUnorderedList,
                    title: 'Generic List'
                },
                {
                    name: 'ordered-list',
                    className: 'ph ph-list-numbers',
                    action: EasyMDE.toggleOrderedList,
                    title: 'Numbered List'
                },
                {
                    name: 'code',
                    className: 'ph ph-code',
                    action: EasyMDE.toggleCodeBlock,
                    title: 'Code'
                },
                {
                    name: 'quote',
                    className: 'ph ph-quotes',
                    action: EasyMDE.toggleBlockquote,
                    title: 'Quote'
                },
                {
                    name: 'link',
                    className: 'ph ph-link',
                    action: EasyMDE.drawLink,
                    title: 'Create Link'
                },
                {
                    name: 'horizontal-rule',
                    className: 'ph ph-minus',
                    action: EasyMDE.drawHorizontalRule,
                    title: 'Insert Horizontal Line'
                },
                '|',
            ];

            if (this.getAcl().check('File', 'read')) {
                items.push({
                    name: "selectImage",
                    action: (editor) => {
                        this.notify('Loading...');
                        this.createView('selectFileDialog', this.getMetadata().get('clientDefs.File.modalViews.select') || 'views/modals/select-records', {
                            scope: 'File',
                            filters: {
                                queryBuilder: {
                                    condition: 'AND',
                                    rules: [
                                        {
                                            id: 'typeId',
                                            field: 'typId',
                                            operator: 'in',
                                            value: ['a_image', 'a_favicon']
                                        }
                                    ]
                                }
                            },
                        }, view => {
                            view.render();
                            this.notify(false);
                            this.listenTo(view, 'select', model => {
                                this.notify('Loading...');
                                $.ajax({
                                    type: 'POST',
                                    url: 'File/action/prepareForRichEditor',
                                    contentType: "application/json",
                                    data: JSON.stringify({
                                        fileId: model.get('id')
                                    }),
                                }).done(response => {
                                    this.notify(false);
                                    const file = new File([], model.get('name'));
                                    file.url = response.sharedUrl;
                                    this.editor.uploadImageUsingCustomFunction(this.uploadImage.bind(this), file)
                                }).error(response => {
                                    this.notify(false);
                                    console.error(response);
                                    Espo.ui.error('Error while selecting file');
                                });
                            });
                        });
                    },
                    className: "ph ph-file-image",
                    title: this.translate('Select Image')
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
                    className: "ph ph-download",
                    title: this.translate('Upload Image')
                });
            }

            if (items[items.length - 1] !== '|') {
                items.push('|');
            }

            items.push({
                name: 'preview',
                action: editor => {
                    EasyMDE.togglePreview(editor);
                    this.trigger('editor:previewToggled', editor)
                },
                title: "Toggle Preview",
                className: "ph ph-eye",
                noDisable: true
            });

            items.push({
                name: 'guide',
                action: 'https://www.markdownguide.org/basic-syntax/',
                title: "Markdown Guide",
                className: "ph ph-question",
            });

            return items;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            const element = this.$element.get(0);

            if (this.mode === 'edit' && element && !this.readOnly) {
                this.trigger('before:editor:rendered', this.$element);

                this.editor = new EasyMDE({
                    element: element,
                    autoDownloadFontAwesome: false,
                    minHeight: `${this.minHeight}px`,
                    spellChecker: false,
                    nativeSpellcheck: true,
                    inputStyle: 'contenteditable',
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
                            Espo.ui.notify(`Your file exceeded size limit of ${this.getMaxUploadSize() / 1024 / 1024} MB`);
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
                        this.notify('Uploading...');
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
                                this.notify(false);
                                file.url = response.sharedUrl;
                                this.uploadImage(file, onSuccess, onError);
                            }).error(response => {
                                this.notify(false);
                                console.error(response);
                                Espo.ui.error('Error while uploading file');
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });

                const scroller = this.editor.codemirror.getScrollerElement();
                const wrapper = this.editor.codemirror.getWrapperElement();

                if (scroller) {
                    scroller.style.maxHeight = `${this.maxHeight}px`;
                }

                this.trigger('editor:rendered', this.editor);

                this.editor.codemirror.on('focus', (cm, e) => {
                   this.trigger('focus', cm, e);
                });

                this.editor.codemirror.on('change', (cm, change) => {
                    this.model.set(this.name, this.editor.value(), {silent: true});
                });

                this.editor.codemirror.on('keypress', (cm, e) => {
                    this.trigger('editor:keypress', cm, e);
                });

                this.on('editor:previewToggled', editor => {
                    wrapper?.classList.toggle('preview-enabled');
                    scroller?.classList.toggle('hide');
                    this.$el.find('.editor-preview-full.editor-preview').css('max-height', `${this.maxHeight}px`);
                })
            }
        },

        setMode(name) {
            if (this.editor && name !== 'edit') {
                this.editor.cleanup();
                this.editor.toTextArea();
                this.editor = null;
            }

            Dep.prototype.setMode.call(this, name);
        },

        uploadImage(file, onSuccess, onError) {
            if (file.url) {
                onSuccess(file.url);
            }
        },

        empty() {
            if (this.editor) {
                this.editor.codemirror.setValue('');
            }
        }
    });
});
