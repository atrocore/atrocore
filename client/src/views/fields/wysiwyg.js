/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/fields/wysiwyg', ['views/fields/text', 'lib!Summernote'], function (Dep, Summernote) {

    return Dep.extend({

        type: 'wysiwyg',

        detailTemplate: 'fields/wysiwyg/detail',

        editTemplate: 'fields/wysiwyg/edit',

        height: 250,

        rowsDefault: 15,

        seeMoreDisabled: true,

        defaultFilterValue: '',

        setup: function () {
            Dep.prototype.setup.call(this);

            if ('height' in this.params) {
                this.height = this.params.height;
            }

            if ('minHeight' in this.params) {
                this.minHeight = this.params.minHeight;
            }

            this.useIframe = this.params.useIframe || this.useIframe;

            this.buttons = {};
            this.toolbar = this.params.toolbar || [
                ['style', ['style']],
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['list', ['ul', 'ol']],
                ['para', ['paragraph']],
                ['height', ['height']],
                ['table', ['table']],
                ['link', ['link', 'hr']],
                ['image', this.getImageButtons()],
                ['misc', ['codeview', 'fullscreen']]
            ];

            if (!this.params.toolbar) {
                if (this.params.attachmentField) {
                    const buttons = this.getImageButtons();

                    if (buttons) {
                        this.toolbar.push([
                            'attachment',
                            buttons
                        ]);
                    }
                }
            }

            this.listenTo(this.model, 'change:isHtml', function (model) {
                if (this.mode == 'edit') {
                    if (this.isRendered()) {
                        if (!model.has('isHtml') || model.get('isHtml')) {
        		            var value = this.plainToHtml(this.model.get(this.name));
        		            this.model.set(this.name, value);
                            this.enableWysiwygMode();
                        } else {
        		            var value = this.htmlToPlain(this.model.get(this.name));
        		            this.model.set(this.name, value);
                            this.disableWysiwygMode();
                        }
                    }
                }
                if (this.mode == 'detail') {
                    if (this.isRendered()) {
                        this.reRender();
                    }
                }
            }.bind(this));

            this.once('remove', function () {
                if (this.$summernote) {
                    this.$summernote.summernote('destroy');
                }
            });

            this.on('inline-edit-off', function () {
                if (this.$summernote) {
                    this.$summernote.summernote('destroy');
                }
            });

            this.once('remove', function () {
                $(window).off('resize.' + this.cid);
                if (this.$scrollable) {
                    this.$scrollable.off('scroll.' + this.cid + '-edit');
                }
            }.bind(this));
        },

        getImageButtons() {
            const buttons = [];
            const selectImageButton = context => {
                return $.summernote.ui.button({
                    contents: '<i class="glyphicon glyphicon-picture"></i>',
                    tooltip: this.translate('Select Image'),
                    click: () => {
                        this.notify('Loading...');
                        this.createView('selectFileDialog', this.getMetadata().get('clientDefs.File.modalViews.select') || 'views/modals/select-records', {
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
                                    context.invoke('editor.insertImage', response.sharedUrl);
                                }).error(response => {
                                    this.notify(false);
                                    console.error(response);
                                    Espo.ui.error('Error while selecting file');
                                });
                            });
                        });
                    }
                }).render();
            };

            const uploadImageButton = context => {
                return $.summernote.ui.button({
                    contents: '<i class="glyphicon glyphicon-open"></i>',
                    tooltip: this.translate('Upload Image'),
                    click: () => {
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
                                context.invoke('editor.insertImage', entity.sharedUrl);
                            });
                            this.listenToOnce(view, 'close', () => {
                                this.clearView('upload');
                            });
                        });
                    }
                }).render();
            };

            if (this.getAcl().check('File', 'read')) {
                this.buttons['selectImage'] = selectImageButton;
                buttons.push('selectImage');
            }

            if (this.getAcl().check('File', 'create')) {
                this.buttons['uploadImage'] = uploadImageButton;
                buttons.push('uploadImage');
            }

            return buttons;
        },

        data: function () {
            var data = Dep.prototype.data.call(this);

            data.useIframe = this.useIframe;
            data.isPlain = this.model.has('isHtml') && !this.model.get('isHtml');
            return data;
        },

        getValueForDisplay: function () {
            var value = Dep.prototype.getValueForDisplay.call(this);
            return this.sanitizeHtml(value);
        },

        sanitizeHtml: function (value) {
            if (value) {
                value = value.replace(/<[\/]{0,1}(base)[^><]*>/gi, '');
                value = value.replace(/<[\/]{0,1}(script)[^><]*>/gi, '');
                value = value.replace(/<[^><]*(onerror|onclick|onmouseover|onmousedown|onmouseenter|onmouseout|mouseleave|onchange|onblur)=[^><]*>/gi, '');
            }
            return value || '';
        },

        getValueForEdit: function () {
            var value = this.model.get(this.name) || '';
            return this.sanitizeHtml(value);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode == 'edit') {
                this.$summernote = this.$el.find('.summernote');
            }

            var language = this.getConfig().get('language');

            if (!(language in $.summernote.lang)) {
                $.summernote.lang[language] = this.getLanguage().translate('summernote', 'sets');
            }

            if (this.mode == 'edit') {
                if (!this.model.has('isHtml') || this.model.get('isHtml')) {
                    this.enableWysiwygMode();
                } else {
                    this.$element.removeClass('hidden');
                }
            }

            if (this.mode == 'detail') {
                if (!this.model.has('isHtml') || this.model.get('isHtml')) {
                    if (!this.useIframe) {
                        this.$element = this.$el.find('.html-container');
                    } else {
                        this.$el.find('iframe').removeClass('hidden');

                        var $iframe = this.$el.find('iframe');

                        var iframeElement = this.iframe = $iframe.get(0);
                        if (!iframeElement) return;

                        $iframe.load(function () {
                            $iframe.contents().find('a').attr('target', '_blank');
                        });

                        var documentElement = iframeElement.contentWindow.document;

                        var body = this.sanitizeHtml(this.model.get(this.name) || '');

                        var linkElement = iframeElement.contentWindow.document.createElement('link');
                        linkElement.type = 'text/css';
                        linkElement.rel = 'stylesheet';
                        linkElement.href = this.getBasePath() + this.getThemeManager().getIframeStylesheet();

                        body = linkElement.outerHTML + body;

                        documentElement.write(body);
                        documentElement.close();

                        var $body = $iframe.contents().find('html body');

                        var $document = $(documentElement);

                        var processWidth = function () {
                            var bodyElement = $body.get(0);
                            if (bodyElement) {
                                if (bodyElement.clientWidth !== iframeElement.scrollWidth) {
                                    iframeElement.style.height = (iframeElement.scrollHeight + 20) + 'px';
                                }
                            }
                        };

                        var increaseHeightStep = 10;
                        var processIncreaseHeight = function (iteration, previousDiff) {
                            $body.css('height', '');

                            iteration = iteration || 0;

                            if (iteration > 200) {
                                return;
                            }

                            iteration ++;

                            var diff = $document.height() - iframeElement.scrollHeight;

                            if (typeof previousDiff !== 'undefined') {
                                if (diff === previousDiff) {
                                    $body.css('height', (iframeElement.clientHeight - increaseHeightStep) + 'px');
                                    processWidth();
                                    return;
                                }
                            }

                            if (diff) {
                                var height = iframeElement.scrollHeight + increaseHeightStep;
                                iframeElement.style.height = height + 'px';
                                processIncreaseHeight(iteration, diff);
                            } else {
                                processWidth();
                            }
                        };

                        var processHeight = function (isOnLoad) {
                            if (!isOnLoad) {
                                $iframe.css({
                                    overflowY: 'hidden',
                                    overflowX: 'hidden'
                                });

                                iframeElement.style.height = '0px';
                            } else {
                                if (iframeElement.scrollHeight >= $document.height()) {
                                    return;
                                }
                            }

                            var $body = $iframe.contents().find('html body');
                            var height = $body.height();
                            if (height === 0) {
                                height = $body.children(0).height() + 100;
                            }

                            iframeElement.style.height = height + 'px';

                            processIncreaseHeight();

                            if (!isOnLoad) {
                                $iframe.css({
                                    overflowY: 'hidden',
                                    overflowX: 'scroll'
                                });
                            }
                        };

                        $iframe.css({
                            visibility: 'hidden'
                        });
                        setTimeout(function () {
                            processHeight();
                            $iframe.css({
                                visibility: 'visible'
                            });
                            $iframe.load(function () {
                                processHeight(true);
                            });
                        }, 40);

                        var windowWidth = $(window).width();
                        $(window).off('resize.' + this.cid);
                        $(window).on('resize.' + this.cid, function() {
                            if ($(window).width() != windowWidth) {
                                processHeight();
                                windowWidth = $(window).width();
                            }
                        }.bind(this));
                    }

                } else {
                    this.$el.find('.plain').removeClass('hidden');
                }
            }
        },

        enableWysiwygMode: function () {
            if (!this.$element) {
                return;
            }

            this.$element.addClass('hidden');
            this.$summernote.removeClass('hidden');

            const contents = this.getValueForEdit();
            this.$summernote.html(contents);

            this.$summernote.find('style').remove();
            this.$summernote.find('link[ref="stylesheet"]').remove();

            const options = {
                lang: this.getConfig().get('language'),
                callbacks: {
                    onImageUpload: files => {
                        const file = files[0];
                        files.pop();

                        if (!this.getAcl().check('File', 'create')) {
                            Espo.ui.error('You are not allowed to upload images');
                            return;
                        }

                        const extensions = this.getMetadata().get(['app', 'file', 'image', 'extensions']);
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
                                this.$summernote.summernote('insertImage', response.sharedUrl);
                            }).error(response => {
                                this.notify(false);
                                console.error(response);
                                Espo.ui.error('Error while uploading file');
                            });
                        };
                        reader.readAsDataURL(file);
                    },
                    onBlur: function () {
                        this.trigger('change')
                    }.bind(this),
                },
                toolbar: this.toolbar,
                buttons: this.buttons
            };

            if (this.height) {
                options.height = this.height;
            } else {
                var $scrollable = this.$el.closest('.modal-body');
                if (!$scrollable.size()) {
                    $scrollable = $(window);
                }
                this.$scrollable = $scrollable;
                $scrollable.off('scroll.' + this.cid + '-edit');
                $scrollable.on('scroll.' + this.cid + '-edit', function (e) {
                    this.onScrollEdit(e);
                }.bind(this));
            }

            if (this.minHeight) {
                options.minHeight = this.minHeight;
            }

            this.$summernote.summernote(options);

            this.$toolbar = this.$el.find('.note-toolbar');
            this.$area = this.$el.find('.note-editing-area');
        },

        plainToHtml: function (html) {
        	html = html || '';
        	var value = html.replace(/\n/g, '<br>');
        	return value;
        },

        htmlToPlain: function (text) {
            if(text==null) return null;
        	text = text || '';
            var value = text.replace(/<br\s*\/?>/mg, '\n');

            value = value.replace(/<\/p\s*\/?>/mg, '\n\n');

            var $div = $('<div>').html(value);
            $div.find('style').remove();
            $div.find('link[ref="stylesheet"]').remove();

            value =  $div.text();

            return value;
        },

        disableWysiwygMode: function () {
            if (this.$summernote) {
                this.$summernote.summernote('destroy');
                this.$summernote.addClass('hidden');
            }
            this.$element.removeClass('hidden');

            if (this.$scrollable) {
                this.$scrollable.off('scroll.' + this.cid + '-edit');
            }
        },

        fetch: function () {
            var data = {};
            if (!this.model.has('isHtml') || this.model.get('isHtml')) {
                data[this.name] = this.$summernote.summernote('code');
                //check if empty
                if (data[this.name] === '') data[this.name] = null;
            } else {
                data[this.name] = this.$element ? this.$element.val() : null;
                //check if empty
                if (data[this.name]==='') data[this.name] = null;
            }

            if (this.model.has('isHtml')) {
            	if (this.model.get('isHtml')) {
            		data[this.name + 'Plain'] = this.htmlToPlain(data[this.name]);
            	} else {
            		data[this.name + 'Plain'] = data[this.name];
            	}
            }
            return data;
        },

        onScrollEdit: function (e) {
            var $target = $(e.target);
            var toolbarHeight = this.$toolbar.height();
            var toolbarWidth = this.$toolbar.parent().width();
            var edgeTop, edgeTopAbsolute;

            if ($target.get(0) === window.document) {
                var $buttonContainer = $target.find('.detail-button-container:not(.hidden)');
                var offset = $buttonContainer.offset();
                if (offset) {
                    edgeTop = offset.top + $buttonContainer.height();
                    edgeTopAbsolute = edgeTop - $(window).scrollTop();
                }
            } else {
                var offset = $target.offset();
                if (offset) {
                    edgeTop = offset.top;
                    edgeTopAbsolute = edgeTop - $(window).scrollTop();
                }
            }

            var top = this.$el.offset().top;
            var bottom = top + this.$el.height() - toolbarHeight;

            var toStick = false;
            if (edgeTop > top && bottom > edgeTop) {
                toStick = true;
            }

            if (toStick) {
                this.$toolbar.css({
                    top: edgeTopAbsolute + 'px',
                    width: toolbarWidth + 'px'
                });
                this.$toolbar.addClass('sticked');
                this.$area.css({
                    marginTop: toolbarHeight + 'px',
                    backgroundColor: ''
                });
            } else {
                this.$toolbar.css({
                    top: '',
                    width: ''
                });
                this.$toolbar.removeClass('sticked');
                this.$area.css({
                    marginTop: ''
                });
            }
        },

        enable() {
            this.$summernote.summernote('enable')
        },

        disable() {
            this.$summernote.summernote('disable')
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                operators: [
                    'contains',
                    'not_contains',
                    'equal',
                    'not_equal',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this)
            };
        },

    });
});
