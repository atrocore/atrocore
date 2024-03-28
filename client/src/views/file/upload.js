/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/upload', ['views/fields/attachment-multiple', 'lib!MD5'], function (Dep, MD5) {

    return Dep.extend({

        editTemplate: 'file/upload',

        showPreviews: false,

        isUploading: false,

        finalPieceSize: 10 * 1024,

        toLink: {},

        multiUpload: true,

        events: _.extend(Dep.prototype.events, {
                'click a.remove-attachment': function (e) {
                    let $div = $(e.currentTarget).parent();
                    let id = $div.attr('data-id');
                    let hash = $div.attr('data-unique');

                    if (id) {
                        $.ajax({
                            type: 'DELETE',
                            url: `File/${id}?silent=true`,
                            contentType: "application/json"
                        }).done(() => {
                            this.model.trigger('after:file-delete', id);
                        });
                    }

                    let fileList = [];
                    this.fileList.forEach(function (file) {
                        if (file.uniqueId !== hash) {
                            fileList.push(file);
                        }
                    });

                    this.fileList = fileList;
                    delete this.uploadedSize[hash];
                    delete this.filesSize[hash];

                    this.updateProgress();

                    $div.parent().remove();

                    this.$el.find('#upload-input').removeAttr('disabled');
                    this.$el.find('#upload-btn').removeClass('disabled');
                    this.$el.find('#upload-area').removeClass('disabled');

                    if (this.isDone()) {
                        this.model.trigger('updating-ended', {hideNotification: true});
                    }

                    this.model.trigger('after:delete-action', id);
                },

                'click a.retry-upload': function (e) {
                    let $a = $(e.currentTarget);
                    let hash = $a.data('unique');

                    let $div = $a.parent().parent();

                    $div.find('.uploaded-file').removeClass('file-uploading-failed');
                    $div.find('.uploading-message').html(this.translate('Pending...'));

                    this.uploadFiles([this.failedFiles[hash]]);
                    delete this.failedFiles[hash];
                }
            },
        ),

        setup() {
            Dep.prototype.setup.call(this);

            if (this.options.multiUpload === false) {
                this.multiUpload = false;
            }

            this.fileList = [];
            this.uploadedSize = {};
            this.finallyUploadedFiles = {};
            this.filesSize = {};
            this.failedFiles = {};

            this.listenTo(this.model, "click:upload-via-url", () => {
                const $el = $('#upload-url-input');
                const url = $el.val() || '';
                if (url.length < 1 || !this.isURL(url)) {
                    this.notify(this.translate('urlExpected', 'labels', 'File'), 'error');
                } else {
                    $el.val('');
                    fetch(url).then(response => {
                        response.blob().then(file => {
                            file.name = this.getFileNameFromURL(url);
                            this.uploadFiles([file]);
                        });
                    });
                }
            });

            this.listenTo(this.model, "updating-started", function () {
                this.isUploading = true;
            });

            this.listenTo(this.model, "updating-ended", function (data) {
                if (!data || !data.hideNotification) {
                    setTimeout(function () {
                        let failedCount = $('.file-uploading-failed').length;
                        if (failedCount > 0) {
                            let message = this.translate('notAllFilesWereUploaded', 'messages', 'File');
                            message = message.replace('XX', failedCount);
                            message = message.replace('YY', $('.uploaded-file').length);

                            Espo.Ui.notify(message, 'error', 1000 * 120, true);

                            this.afterShowNotification();
                        }
                    }.bind(this), 100);
                }
                this.isUploading = false;
                $('.attachment-upload .progress').hide();
            }.bind(this));
        },

        isURL(str) {
            const urlRegex = /^(?:https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i;
            return urlRegex.test(str);
        },

        getFileNameFromURL(url) {
            const urlObject = new URL(url);
            const pathname = urlObject.pathname;
            return pathname.substring(pathname.lastIndexOf('/') + 1);
        },

        data() {
            return {
                multiUpload: this.multiUpload
            };
        },

        afterShowNotification: function () {
        },

        getPercentCompleted: function () {
            let uploaded = this.getFilesSize();
            if (uploaded === 0) {
                return 0;
            }

            return 100 / uploaded * this.getUploadedSize();
        },

        getMaxUploadSize: function () {
            return (this.getConfig().get('chunkFileSize') || 2) * 1024 * 1024;
        },

        createFileUniqueHash: function (file) {
            return MD5(`${file.name}_${file.size}`);
        },

        slice: function (file, start, end) {
            let slice = file.mozSlice ? file.mozSlice : file.webkitSlice ? file.webkitSlice : file.slice ? file.slice : noop;
            return slice.bind(file)(start, end);
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

        addFileBox: function (file) {
            let $attachments = this.$attachments;
            let removeLink = '<a href="javascript:" class="remove-attachment pull-right"><span class="fas fa-times"></span></a>';
            let $att = $(`<div class="uploaded-file gray-box" data-unique="${file.uniqueId}">`)
                .append(removeLink)
                .append($('<span class="preview">' + file.name + '</span>').css('width', 'cacl(100% - 30px)'));

            let $container = $('<div>').append($att);
            $attachments.append($container);

            let $loading = $('<span class="small uploading-message">' + this.translate('Pending...') + '</span>');
            $container.append($loading);

            return $att;
        },

        uploadFiles: function (files) {
            for (let i = 0; i < files.length; i++) {
                let file = files[i];

                if (file.type === '') {
                    file.type = 'text/plain';
                }

                file['uniqueId'] = this.createFileUniqueHash(file);

                if (!this.isFileInList(file['uniqueId'])) {
                    let $attachmentBox = $(`.uploaded-file[data-unique='${file['uniqueId']}']`);
                    if ($attachmentBox.length > 0) {
                        file['attachmentBox'] = $attachmentBox;
                    } else {
                        file['attachmentBox'] = this.addFileBox(file);
                    }

                    this.fileList.push(file);
                    this.filesSize[file.uniqueId] = file.size;
                    this.uploadedSize[file.uniqueId] = [];
                    this.finallyUploadedFiles[file.uniqueId] = this.finalPieceSize;

                    this.updateProgress();
                }
            }

            if (!this.isUploading) {
                this.model.trigger('updating-started');
                this.updateProgress();
                this.createAttachments();
            }

            // clear input
            $('.attachment-upload input.file').val('');
        },

        createAttachments: function () {
            if (!this.isModalOpen()) {
                return;
            }

            if (this.fileList.length === 0 || !this.isUploading) {
                this.model.trigger('updating-ended');
                return;
            }

            let file = this.fileList.shift();

            if (!this.isFileInList(file.uniqueId)) {
                this.createAttachments();
                return;
            }

            file.attachmentBox.parent().find('.uploading-message').html(this.translate('Uploading...'));

            if (file.size > this.getMaxUploadSize()) {
                this.chunkCreateAttachments(file, () => {
                    this.createAttachments();
                });
            } else {
                let fileReader = new FileReader();
                const id = this.generateId();
                fileReader.onload = e => {
                    $.ajax({
                        type: 'POST',
                        url: 'File?silent=true',
                        contentType: "application/json",
                        data: JSON.stringify(_.extend(this.model.attributes, {
                            id: id,
                            name: file.name,
                            fileSize: file.size,
                            piecesCount: undefined,
                            fileContents: e.target.result
                        })),
                    }).done(response => {
                        this.pushPieceSize(file, file.size);
                        this.finallyUploadedFiles[file.uniqueId] = 0;
                        this.updateProgress();
                        this.uploadSuccess(file, response);
                        this.createAttachments();
                    }).error(response => {
                        this.pushPieceSize(file, file.size);
                        this.updateProgress();
                        this.uploadFailed(file, response);
                        this.createAttachments();
                    });
                };
                fileReader.readAsDataURL(file);
            }
        },

        generateId() {
            return 'a' + (MD5('atro_' + (new Date()).getTime())).substring(0, 16);
        },

        chunkCreateAttachments: function (file, callback) {
            const sliceSize = this.getMaxUploadSize();

            this.streams = this.getConfig().get('fileUploadStreamCount') || 3;

            this.setProgressMessage(file);

            const id = this.generateId();

            // create file pieces
            this.pieces = [];
            this.createFilePieces(file, sliceSize, 0, 1);
            this.piecesCount = this.pieces.length;

            this.uploadedChunks = [];

            let promiseList = [];

            let stream = 1;
            while (stream <= this.streams) {
                let pieces = [];
                this.pieces.forEach(row => {
                    if (row.stream === stream) {
                        pieces.push(row);
                    }
                });

                if (pieces.length > 0) {
                    promiseList.push(new Promise(resolve => {
                        this.sendChunk(file, pieces, resolve, id);
                    }));
                }
                stream++;
            }

            Promise.all(promiseList).then(() => {
                callback();
            });
        },

        isModalOpen: function () {
            return $('.attachment-upload').is(':visible');
        },

        sendChunk: function (file, pieces, resolve, id) {
            if (!this.isModalOpen()) {
                return;
            }

            if (pieces.length === 0 || !this.isUploading || !this.filesSize[file.uniqueId]) {
                resolve();
                return;
            }

            const item = pieces.shift();

            const reader = new FileReader();
            reader.readAsDataURL(item.piece);

            reader.onloadend = () => {
                // if chunk already uploaded
                if (this.uploadedChunks.includes(item.start.toString())) {
                    this.pushPieceSize(file, item.piece.size);
                    this.setProgressMessage(file);
                    this.updateProgress();
                    this.sendChunk(file, pieces, resolve, id);
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: 'File?silent=true',
                    contentType: "application/json",
                    data: JSON.stringify(_.extend(this.model.attributes, {
                        id: id,
                        fileUniqueHash: file.uniqueId,
                        start: item.start,
                        piece: reader.result,
                        piecesCount: this.pieces.length,
                        name: file.name,
                        fileSize: file.size,
                        fileContents: undefined
                    })),
                }).done(entity => {
                    this.uploadedChunks = entity.chunks;
                    if (entity.id) {
                        this.pieces = [];
                        this.uploadedSize[file.uniqueId] = [this.filesSize[file.uniqueId]];
                        this.finallyUploadedFiles[file.uniqueId] = 0;
                        this.setProgressMessage(file);
                        this.updateProgress();
                        this.uploadSuccess(file, entity);
                        resolve();
                    } else {
                        this.pushPieceSize(file, item.piece.size);
                        this.setProgressMessage(file);
                        this.updateProgress();
                        this.sendChunk(file, pieces, resolve, id);
                    }
                }).error(response => {
                    this.uploadFailed(file, response);
                    resolve();
                });
            }
        },

        setProgressMessage: function (file) {
            let piecesSize = 0;
            if (this.isFileInList(file.uniqueId)) {
                piecesSize = this.uploadedSize[file.uniqueId].reduce((a, b) => a + b, 0);
            }

            let fileSize = typeof this.filesSize[file.uniqueId] !== 'undefined' ? this.filesSize[file.uniqueId] : 0;

            let total = fileSize + this.finallyUploadedFiles[file.uniqueId];
            let percent = piecesSize / total * 100;

            if (!file.attachmentBox.data('id')) {
                file.attachmentBox.parent().find('.uploading-message').html(this.translate('Uploading...') + ' <span class="uploading-progress-message">' + percent.toFixed(0) + '%</span>');
            }
        },

        uploadSuccess: function (file, entity) {
            if (!this.multiUpload) {
                this.$el.find('#upload-input').attr('disabled', 'disabled');
                this.$el.find('#upload-btn').addClass('disabled');
                this.$el.find('#upload-area').addClass('disabled');
            }

            this.model.trigger('after:file-upload', entity);
            const $message = file.attachmentBox.parent().find('.uploading-message');

            $message.html('');

            if (entity !== null) {
                file.attachmentBox.attr('data-id', entity.id).addClass('file-uploading-success');
                file.attachmentBox.find('.remove-attachment').attr('title', this.translate('Delete')).html('<span class="fas fa-trash"></span>');
                file.attachmentBox.find('.preview').html(`<a target="_blank" href="/#File/view/${entity.id}">${entity.name}</a>`);
                if (entity.duplicate) {
                    let message = this.translate('fileHasDuplicate', 'messages', 'File').replace('{{id}}', entity.duplicate.id);
                    $message.html(message);
                }
            }

            setTimeout(function () {
                if (this.isDone()) {
                    this.model.trigger('updating-ended');
                }
            }.bind(this), 100);
        },

        uploadFailed: function (file, response) {
            this.failedFiles[file.uniqueId] = file;

            let html = response.getResponseHeader('X-Status-Reason') || this.translate('fileCouldNotBeUploaded', 'messages', 'File');

            if (response.status !== 400) {
                html += ` <a href="javascript:" class="retry-upload" data-unique="${file.uniqueId}">${this.translate('retry', 'labels', 'File')}</a>`;
            }

            file.attachmentBox.parent().find('.uploading-message').html(html);
            file.attachmentBox.addClass('file-uploading-failed');

            delete this.uploadedSize[file.uniqueId];
            delete this.filesSize[file.uniqueId];

            this.updateProgress();

            setTimeout(function () {
                if (this.isDone()) {
                    this.model.trigger('updating-ended');
                }
            }.bind(this), 100);
        },

        isDone: function () {
            return this.getFilesSize() === this.getUploadedSize();
        },

        getFilesSize: function () {
            let filesSize = 0;
            $.each(this.filesSize, function (hash, size) {
                filesSize += size + this.finallyUploadedFiles[hash];
            }.bind(this));

            return filesSize;
        },

        getUploadedSize: function () {
            let uploadedSize = 0;
            $.each(this.uploadedSize, function (hash, pieces) {
                pieces.forEach(function (size) {
                    uploadedSize += size;
                });
            });

            return uploadedSize;
        },

        updateProgress: function () {
            let $progress = $('.attachment-upload .progress .progress-bar');
            let percentCompleted = this.getPercentCompleted();

            if (percentCompleted !== 100) {
                percentCompleted = Math.round(percentCompleted);
                $progress.parent().show();
                $progress.css('width', percentCompleted + '%').html('&nbsp;' + percentCompleted + '% ' + this.translate('uploaded', 'labels', 'File'));
            } else {
                $progress.parent().hide();
            }
        },

        pushPieceSize: function (file, size) {
            const hash = file.uniqueId;
            if (this.isFileInList(hash)) {
                if (!this.uploadedSize[hash] || this.uploadedSize[hash][0] !== file.size) {
                    this.uploadedSize[hash].push(size);
                }
                return true;
            }

            return false;
        },

        isFileInList: function (hash) {
            return typeof this.uploadedSize[hash] !== 'undefined'
        },

    })
});