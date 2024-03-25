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

Espo.define('views/fields/file', ['views/fields/link', 'lib!MD5'], function (Dep, MD5) {

    return Dep.extend({

        type: 'file',

        listTemplate: 'fields/file/list',

        detailTemplate: 'fields/file/detail',

        // editTemplate: 'fields/file/edit',

        showPreview: false,

        accept: false,

        previewTypeList: [
            'image/jpeg',
            'image/png',
            'image/gif',
        ],

        defaultType: false,

        previewSize: 'small',

        validations: ['ready', 'required'],

        searchTypeList: ['isNotEmpty', 'isEmpty'],

        streams: 3,

        pieces: [],

        pieceNumber: 0,

        piecesTotal: 0,

        events: {
            'click a.remove-attachment': function (e) {
                var $div = $(e.currentTarget).parent();
                this.deleteAttachment();
                $div.parent().remove();
                this.$el.find('.attachment-button').removeClass('hidden');
            },
            'change input.file': function (e) {
                var $file = $(e.currentTarget);
                var files = e.currentTarget.files;
                if (files.length) {
                    this.uploadFile(files[0]);
                    $file.replaceWith($file.clone(true));
                }
            },
            'click a[data-action="showImagePreview"]': function (e) {
                e.preventDefault();

                var id = this.model.get(this.idName);
                this.createView('preview', 'views/modals/image-preview', {
                    id: id,
                    model: this.model,
                    name: this.model.get(this.nameName),
                    downloadUrl: this.getFilePathsData().download,
                    thumbnailUrl: this.getFilePathsData().thumbnails.large,
                }, function (view) {
                    view.render();
                });
            },
            'click a.action[data-action="insertFromSource"]': function (e) {
                var name = $(e.currentTarget).data('name');
                this.insertFromSource(name);
            }
        },

        data: function () {
            return _.extend({valueIsSet: this.model.has(this.idName)}, Dep.prototype.data.call(this));
        },

        showValidationMessage: function (msg, selector) {
            var $label = this.$el.find('label');
            var title = $label.attr('title');
            $label.attr('title', '');
            Dep.prototype.showValidationMessage.call(this, msg, selector);
            $label.attr('title', title);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.idName) == null) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    var $target;
                    if (this.isUploading) {
                        $target = this.$el.find('.gray-box');
                    } else {
                        $target = this.$el.find('.attachment-button label');
                    }

                    this.showValidationMessage(msg, $target);
                    return true;
                }
            }
        },

        validateReady: function () {
            if (this.isUploading) {
                var $target = this.$el.find('.gray-box');
                var msg = this.translate('fieldIsUploading', 'messages').replace('{field}', this.getLabelText());
                this.showValidationMessage(msg, $target);
                return true;
            }
        },

        setup: function () {
            this.nameName = this.name + 'Name';
            this.namePathsData = this.name + 'PathsData';
            this.idName = this.name + 'Id';
            this.typeName = this.name + 'Type';
            this.foreignScope = 'File';
            this.streams = this.getConfig().get('fileUploadStreamCount') || 3;
            this.pieces = [];

            this.previewSize = this.options.previewSize || this.params.previewSize || this.previewSize;

            var sourceDefs = this.getMetadata().get(['clientDefs', 'Attachment', 'sourceDefs']) || {};

            this.sourceList = Espo.Utils.clone(this.params.sourceList || []).filter(function (item) {
                if (!(item in sourceDefs)) return true;
                var defs = sourceDefs[item];
                if (defs.configCheck) {
                    var configCheck = defs.configCheck;
                    if (configCheck) {
                        var arr = configCheck.split('.');
                        if (this.getConfig().getByPath(arr)) {
                            return true;
                        }
                    }
                }
            }, this);

            if ('showPreview' in this.params) {
                this.showPreview = this.params.showPreview;
            }

            if ('accept' in this.params) {
                this.accept = this.params.accept;
            }

            if (this.accept) {
                this.acceptAttribue = this.accept.join('|');
            }

            if (this.mode === 'edit') {
                this.addActionHandler('selectLink', function () {
                    this.notify('Loading...');
                    this.createView('dialog', this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView, {
                        scope: this.foreignScope,
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        boolFilterData: this.getBoolFilterData(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.mandatorySelectAttributeList,
                        forceSelectAllAttributes: this.forceSelectAllAttributes
                    }, function (view) {
                        view.render();
                        this.notify(false);
                        this.listenToOnce(view, 'select', function (model) {
                            this.clearView('dialog');
                            this.select(model);
                        }, this);
                    }, this);
                });

                this.addActionHandler('clearLink', function () {
                    Dep.prototype.clearLink.call(this);
                });
            }

            this.once('remove', function () {
                if (this.resizeIsBeingListened) {
                    $(window).off('resize.' + this.cid);
                }
            }.bind(this));
        },

        initInlineActions: function () {
            this.listenTo(this, 'after:render', this.initDownloadIcon, this);

            Dep.prototype.initInlineActions.call(this);
        },

        afterRender: function () {
            if (this.mode == 'edit') {
                this.$attachment = this.$el.find('div.attachment');

                this.$elementId = this.$el.find('input[name="' + this.idName + '"]');
                this.$elementName = this.$el.find('input[name="' + this.nameName + '"]');

                var name = this.model.get(this.nameName);
                var type = this.model.get(this.typeName) || this.defaultType;
                var id = this.model.get(this.idName);
                if (id) {
                    this.addAttachmentBox(name, type, id);
                }

                this.$el.off('drop');
                this.$el.off('dragover');
                this.$el.off('dragleave');

                this.$el.on('drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var e = e.originalEvent;
                    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                        this.uploadFile(e.dataTransfer.files[0]);
                    }
                }.bind(this));

                this.$el.on('dragover', function (e) {
                    e.preventDefault();
                }.bind(this));
                this.$el.on('dragleave', function (e) {
                    e.preventDefault();
                }.bind(this));
            }

            if (this.mode == 'search') {
                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);
            }

            if (this.mode === 'detail') {
                if (this.previewSize === 'large') {
                    this.handleResize();
                    this.resizeIsBeingListened = true;
                    $(window).on('resize.' + this.cid, function () {
                        this.handleResize();
                    }.bind(this));
                }
            }
        },

        initDownloadIcon: function () {
            const $cell = this.getCellElement();

            $cell.find('.fa-download').parent().remove();

            const id = this.model.get(this.idName);
            if (!id) {
                return;
            }

            const $editLink = $('<a href="' + this.getDownloadUrl(id) + '" class="pull-right hidden" download><span class="fas fa-download fa-sm"></span></a>');

            $cell.prepend($editLink);

            $cell.on('mouseenter', function (e) {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    $editLink.removeClass('hidden');
                }
            }.bind(this)).on('mouseleave', function (e) {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $editLink.addClass('hidden');
                }
            }.bind(this));
        },

        handleResize: function () {
            var width = this.$el.width();
            this.$el.find('img.image-preview').css('maxWidth', width + 'px');
        },

        getEditPreview: function (name, type, id) {
            return name;
        },

        hasPreview: function (name) {
            const hasPreviewExtensions = this.getMetadata().get('app.file.image.extensions') || [];
            const fileExt = (name || '').split('.').pop().toLowerCase();

            return $.inArray(fileExt, hasPreviewExtensions) !== -1;
        },

        getValueForDisplay: function () {
            if (this.mode === 'detail' || this.mode === 'list') {
                let id = this.model.get(this.idName);
                let name = this.model.get(this.nameName);

                if (!id) {
                    return false;
                }

                if (this.hasPreview(name) && this.getImageUrl(id)) {
                    return '<div class="attachment-preview"><a data-action="showImagePreview" data-id="' + id + '" href="' + this.getImageUrl(id) + '"><img src="' + this.getImageUrl(id, this.previewSize) + '" class="image-preview"></a></div>';
                }

                return '<span class="glyphicon glyphicon-paperclip small"></span> <a href="' + this.getDownloadUrl(id) + '" target="_BLANK">' + Handlebars.Utils.escapeExpression(name) + '</a>';
            }
        },

        getFilePathsData: function () {
            return this.model.get(this.namePathsData);
        },

        isCalledForList: function () {
            let view = this.getParentView();
            if (view) {
                view = view.getParentView();
                if (view && view.type === 'list') {
                    return true;
                }
            }

            return false;
        },

        getImageUrl: function (id, size) {
            let data = this.getFilePathsData();
            if (!data) {
                return '';
            }

            let path = data['download'];
            if (size) {
                if (this.isCalledForList()) {
                    size = 'small';
                }

                path = data.thumbnails[size];
            }

            return this.getBasePath() + '/' + path;
        },

        getDownloadUrl: function (id) {
            let data = this.getFilePathsData();
            if (!data) {
                return this.getBasePath() + '?entryPoint=download&id=' + id;
            }

            return this.getBasePath() + data['download'];
        },

        deleteAttachment: function () {
            var id = this.model.get(this.idName);
            var o = {};
            o[this.idName] = null;
            o[this.nameName] = null;
            this.model.set(o);

            this.$attachment.empty();

            if (id) {
                if (this.model.isNew()) {
                    this.getModelFactory().create('Attachment', function (attachment) {
                        attachment.id = id;
                        attachment.destroy();
                    });
                }
            }
        },

        setAttachment: function (attachment) {
            var arr = _.clone(this.model.get(this.idsName));
            var o = {};
            o[this.idName] = attachment.id;
            o[this.nameName] = attachment.get('name');
            o[this.namePathsData] = attachment.pathsData;
            this.model.set(o);
        },

        uploadFile: function (file) {
            let isCanceled = false;

            if (file.size > this.getMaxUploadSize()) {
                this.chunkUploadFile(file);
                return;
            }

            this.isUploading = true;

            this.getModelFactory().create('Attachment', function (attachment) {
                var $attachmentBox = this.addAttachmentBox(file.name, file.type);

                this.$el.find('.attachment-button').addClass('hidden');

                $attachmentBox.find('.remove-attachment').on('click.uploading', function () {
                    isCanceled = true;
                    this.$el.find('.attachment-button').removeClass('hidden');
                    this.isUploading = false;
                }.bind(this));

                var fileReader = new FileReader();
                fileReader.onload = function (e) {
                    this.handleFileUpload(file, e.target.result, function (result, fileParams) {
                        attachment.set('name', fileParams.name);
                        attachment.set('type', fileParams.type || 'text/plain');
                        attachment.set('size', fileParams.size);
                        attachment.set('role', 'Attachment');
                        attachment.set('relatedType', this.model.name);
                        attachment.set('file', result);
                        attachment.set('field', this.name);
                        attachment.set('modelAttributes', this.model.attributes);

                        attachment.save({}, {timeout: 0}).then(function (response) {
                            this.isUploading = false;
                            if (!isCanceled) {
                                $attachmentBox.trigger('ready');
                                this.setAttachment(attachment);
                            }
                        }.bind(this)).fail(function () {
                            $attachmentBox.remove();
                            this.$el.find('.uploading-message').remove();
                            this.$el.find('.attachment-button').removeClass('hidden');
                            this.isUploading = false;
                        }.bind(this));
                    }.bind(this));
                }.bind(this);
                fileReader.readAsDataURL(file);
            }, this);
        },

        getMaxUploadSize: function () {
            return (this.getConfig().get('chunkFileSize') || 2) * 1024 * 1024;
        },

        chunkUploadFile: function (file) {
            const $attachmentBox = this.addAttachmentBox(file.name, file.type);
            this.$el.find('.attachment-button').addClass('hidden');
            $attachmentBox.find('.remove-attachment').on('click.uploading', function () {
                this.$el.find('.attachment-button').removeClass('hidden');
                this.isUploading = false;
                this.pieces = [];
            }.bind(this));

            this.$el.parent().find('.inline-cancel-link').click(function () {
                this.isUploading = false;
                this.pieces = [];
            }.bind(this));
            this.$el.parent().find('.inline-save-link').click(function () {
                this.isUploading = false;
                this.pieces = [];
            }.bind(this));

            const chunkId = this.createFileUniqueHash(file);
            const sliceSize = this.getMaxUploadSize();

            this.piecesTotal = Math.ceil(file.size / sliceSize);
            this.pieceNumber = 0;
            this.isUploading = true;
            this.uploadedChunks = [];

            this.setProgressMessage(this.pieceNumber, this.piecesTotal);

            // create file pieces
            this.createFilePieces(file, sliceSize, 0, 1);

            let stream = 1;
            while (stream <= this.streams) {
                let pieces = [];
                this.pieces.forEach(function (row) {
                    if (row.stream === stream) {
                        pieces.push(row);
                    }
                });
                this.sendChunk(file, pieces, chunkId, $attachmentBox);
                stream++;
            }
        },

        createFilePieces: function (file, sliceSize, start, stream) {
            let end = start + sliceSize;

            if (file.size - end < 0) {
                end = file.size;
            }

            this.pieces.push({stream: stream, start: start, piece: this.slice(file, start, end)});

            if (end < file.size) {
                start += sliceSize;

                stream++;
                if (stream > this.streams) {
                    stream = 1;
                }

                this.createFilePieces(file, sliceSize, start, stream);
            }
        },

        slice: function (file, start, end) {
            let slice = file.mozSlice ? file.mozSlice : file.webkitSlice ? file.webkitSlice : file.slice ? file.slice : noop;
            return slice.bind(file)(start, end);
        },

        setProgressMessage: function (current, piecesTotal) {
            // prepare total
            const total = piecesTotal + 1;

            let percent = current / total * 100;
            $('.uploading-progress-message').html(percent.toFixed(0) + '%');
        },

        sendChunk: function (file, pieces, chunkId, $attachmentBox) {
            if (!this.isUploading) {
                return;
            }

            if (pieces.length === 0) {
                return;
            }

            const item = pieces.shift();

            const reader = new FileReader();
            reader.readAsDataURL(item.piece);

            reader.onloadend = function () {
                if (this.uploadedChunks.indexOf(item.start.toString()) !== -1) {
                    this.onChunkSaved(file, pieces, chunkId, $attachmentBox);
                } else {
                    $.ajax({
                        type: 'POST',
                        url: 'Attachment/action/CreateChunks?silent=true',
                        contentType: "application/json",
                        data: JSON.stringify({
                            chunkId: chunkId,
                            start: item.start,
                            piece: reader.result,
                            piecesCount: this.pieces.length,
                            name: file.name,
                            type: file.type || 'text/plain',
                            size: file.size,
                            role: 'Attachment',
                            relatedType: this.model.name,
                            field: this.name,
                            modelAttributes: this.model.attributes
                        }),
                    }).done(function (data) {
                        this.uploadedChunks = data.chunks;
                        this.onChunkSaved(file, pieces, chunkId, $attachmentBox);

                        if (data.attachment) {
                            this.model.set(this.namePathsData, data.attachment.pathsData);
                            this.model.set(this.nameName, data.attachment.name);
                            this.model.set(this.idName, data.attachment.id);
                            this.isUploading = false;
                            this.pieces = [];
                        }
                    }.bind(this)).error(function (data) {
                        this.chunkUploadFailed($attachmentBox, data);
                    }.bind(this));
                }
            }.bind(this)
        },

        onChunkSaved: function (file, pieces, chunkId, $attachmentBox) {
            this.pieceNumber++;
            this.setProgressMessage(this.pieceNumber, this.piecesTotal);

            if (pieces.length > 0) {
                this.sendChunk(file, pieces, chunkId, $attachmentBox);
            }
        },

        chunkUploadFailed: function ($attachmentBox, response) {
            $attachmentBox.remove();
            this.$el.find('.uploading-message').remove();
            this.$el.find('.attachment-button').removeClass('hidden');

            let message = response.getResponseHeader('X-Status-Reason') || this.translate('chunkUploadFailed', 'exceptions', 'Attachment');
            Espo.Ui.notify(message, 'error', 1000 * 120, true);

            this.isUploading = false;
            this.pieces = [];
        },

        handleFileUpload: function (file, contents, callback) {
            var params = {
                name: file.name,
                type: file.type,
                size: file.size
            };
            callback(contents, params);
        },

        addAttachmentBox: function (name, type, id) {
            this.$attachment.empty();

            name = Handlebars.Utils.escapeExpression(name);

            var self = this;

            var removeLink = '<a href="javascript:" class="remove-attachment pull-right"><span class="fas fa-times"></span></a>';

            var preview = name;
            if (this.showPreview && id) {
                preview = this.getEditPreview(name, type, id);
            }

            var $att = $('<div>').append(removeLink)
                .append($('<span class="preview">' + preview + '</span>').css('width', 'cacl(100% - 30px)'))
                .addClass('gray-box');

            var $container = $('<div>').append($att);
            this.$attachment.append($container);

            if (!id) {
                var $loading = $('<span class="small uploading-message">' + this.translate('Uploading...') + ' <span class="uploading-progress-message"></span></span>');
                $container.append($loading);
                $att.on('ready', function () {
                    $loading.html(self.translate('Ready'));
                });
            }

            return $att;
        },

        insertFromSource: function (source) {
            var viewName =
                this.getMetadata().get(['clientDefs', 'Attachment', 'sourceDefs', source, 'insertModalView']) ||
                this.getMetadata().get(['clientDefs', source, 'modalViews', 'select']) ||
                'views/modals/select-records';

            if (viewName) {
                this.notify('Loading...');

                var filters = null;
                if (('getSelectFilters' + source) in this) {
                    filters = this['getSelectFilters' + source]();

                    if (this.model.get('parentId') && this.model.get('parentType') === 'Account') {
                        if (this.getMetadata().get(['entityDefs', source, 'fields', 'account', 'type']) === 'link') {
                            filters = {
                                account: {
                                    type: 'equals',
                                    field: 'accountId',
                                    value: this.model.get('parentId'),
                                    valueName: this.model.get('parentName')
                                }
                            };
                        }
                    }
                }
                var boolFilterList = this.getMetadata().get(['clientDefs', 'Attachment', 'sourceDefs', source, 'boolFilterList']);
                if (('getSelectBoolFilterList' + source) in this) {
                    boolFilterList = this['getSelectBoolFilterList' + source]();
                }
                var primaryFilterName = this.getMetadata().get(['clientDefs', 'Attachment', 'sourceDefs', source, 'primaryFilter']);
                if (('getSelectPrimaryFilterName' + source) in this) {
                    primaryFilterName = this['getSelectPrimaryFilterName' + source]();
                }
                this.createView('insertFromSource', viewName, {
                    scope: source,
                    createButton: false,
                    filters: filters,
                    boolFilterList: boolFilterList,
                    primaryFilterName: primaryFilterName,
                    multiple: false
                }, function (view) {
                    view.render();
                    this.notify(false);
                    this.listenToOnce(view, 'select', function (modelList) {
                        if (Object.prototype.toString.call(modelList) !== '[object Array]') {
                            modelList = [modelList];
                        }
                        modelList.forEach(function (model) {
                            if (model.name === 'Attachment') {
                                this.setAttachment(model);
                            } else {
                                this.ajaxPostRequest(source + '/action/getAttachmentList', {
                                    id: model.id
                                }).done(function (attachmentList) {
                                    attachmentList.forEach(function (item) {
                                        this.getModelFactory().create('Attachment', function (attachment) {
                                            attachment.set(item);
                                            this.setAttachment(attachment, true);
                                        }, this);
                                    }, this);
                                }.bind(this));
                            }
                        }, this);
                    });
                }, this);
                return;
            }
        },

        createFileUniqueHash: function (file) {
            return MD5(`${file.name}_${file.size}`);
        },

    });
});
