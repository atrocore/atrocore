/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/upload', ['views/fields/attachment-multiple', 'views/fields/file', 'lib!MD5'], function (Dep, File, MD5) {

    return Dep.extend({

        editTemplate: 'file/upload',

        showPreviews: false,

        isUploading: false,

        finalPieceSize: 10 * 1024,

        toLink: {},

        events: _.extend(Dep.prototype.events, {
                'click a.remove-attachment': function (e) {
                    let $div = $(e.currentTarget).parent();
                    let id = $div.attr('data-id');
                    let hash = $div.attr('data-unique');

                    if (id) {
                        // $.ajax({
                        //     type: 'DELETE',
                        //     url: `Attachment/${id}?silent=true`,
                        //     contentType: "application/json"
                        // });
                        //
                        // let filesIds = [];
                        // (this.model.get('filesIds') || []).forEach(function (fileId) {
                        //     if (fileId !== id) {
                        //         filesIds.push(fileId);
                        //     }
                        // });
                        // this.model.set('filesIds', filesIds, {silent: true});
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

                    // let assetsForRelate = this.model.get('assetsForRelate') || {};
                    // if (assetsForRelate[hash]) {
                    //     delete assetsForRelate[hash];
                    //     this.model.set('assetsForRelate', assetsForRelate, {silent: true});
                    // }

                    this.updateProgress();

                    $div.parent().remove();

                    if (this.isDone()) {
                        this.model.trigger('updating-ended', {hideNotification: true});
                    }
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

            if (this.model.get('massCreate')) this.model.set('name', 'massUpload', {silent: true});

            this.fileList = [];
            this.uploadedSize = {};
            this.finallyUploadedFiles = {};
            this.filesSize = {};
            this.failedFiles = {};

            this.listenTo(this.model, "updating-started", function () {
                this.isUploading = true;
            });

            this.listenTo(this.model, "updating-ended", function (data) {
                if (!data || !data.hideNotification) {
                    setTimeout(function () {
                        let failedCount = $('.file-uploading-failed').length;
                        if (failedCount > 0) {
                            let message = this.translate('notAllAssetsWereUploaded', 'messages', 'Asset');
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
            return File.prototype.slice.call(this, file, start, end);
        },

        createFilePieces: function (file, sliceSize, start, stream) {
            return File.prototype.createFilePieces.call(this, file, sliceSize, start, stream);
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
                fileReader.onload = e => {
                    $.ajax({
                        type: 'POST',
                        url: 'Attachment?silent=true',
                        contentType: "application/json",
                        data: JSON.stringify({
                            name: file.name,
                            type: file.type || 'text/plain',
                            size: file.size,
                            relatedType: 'Asset',
                            role: 'Attachment',
                            file: e.target.result,
                            field: this.name,
                            modelAttributes: this.model.attributes
                        }),
                    }).done(response => {
                        this.pushPieceSize(file.uniqueId, file.size);
                        this.finallyUploadedFiles[file.uniqueId] = 0;
                        this.updateProgress();
                        this.uploadSuccess(file, response);
                        this.createAttachments();
                    }).error(response => {
                        this.pushPieceSize(file.uniqueId, file.size);
                        this.updateProgress();
                        this.uploadFailed(file, response);
                        this.createAttachments();
                    });
                };
                fileReader.readAsDataURL(file);
            }
        },

        chunkCreateAttachments: function (file, callback) {
            const sliceSize = this.getMaxUploadSize();

            this.streams = this.getConfig().get('fileUploadStreamCount') || 3;

            this.setProgressMessage(file);

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
                        this.sendChunk(file, pieces, resolve);
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

        sendChunk: function (file, pieces, resolve) {
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
                    this.pushPieceSize(file.uniqueId, item.piece.size);
                    this.setProgressMessage(file);
                    this.updateProgress();
                    this.sendChunk(file, pieces, resolve);
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: 'Attachment/action/CreateChunks?silent=true',
                    contentType: "application/json",
                    data: JSON.stringify({
                        chunkId: file.uniqueId,
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
                }).done(response => {
                    this.uploadedChunks = response.chunks;
                    if (response.attachment) {
                        this.pieces = [];
                        this.uploadedSize[file.uniqueId] = [this.filesSize[file.uniqueId]];
                        this.finallyUploadedFiles[file.uniqueId] = 0;
                        this.setProgressMessage(file);
                        this.updateProgress();
                        this.uploadSuccess(file, response.attachment);
                        resolve();
                    } else {
                        this.pushPieceSize(file.uniqueId, item.piece.size);
                        this.setProgressMessage(file);
                        this.updateProgress();
                        this.sendChunk(file, pieces, resolve);
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

            file.attachmentBox.parent().find('.uploading-message').html(this.translate('Uploading...') + ' <span class="uploading-progress-message">' + percent.toFixed(0) + '%</span>');
        },

        uploadSuccess: function (file, attachment) {
            file.attachmentBox.parent().find('.uploading-message').remove();

            if (attachment !== null) {
                file.attachmentBox.attr('data-id', attachment.id);
            }

            let filesIds = this.model.get('filesIds') || [];
            filesIds.push(attachment.id);

            let filesNames = this.model.get('filesNames') || {};
            filesNames[attachment.id] = attachment.name;

            this.model.set('filesIds', filesIds, {silent: true});
            this.model.set('filesNames', filesNames, {silent: true});

            setTimeout(function () {
                if (this.isDone()) {
                    this.model.trigger('updating-ended');
                }
            }.bind(this), 100);
        },

        uploadFailed: function (file, response) {
            this.failedFiles[file.uniqueId] = file;

            let html = response.getResponseHeader('X-Status-Reason') || this.translate('assetCouldNotBeUploaded', 'messages', 'Asset');

            if (response.getResponseHeader('X-Status-Reason-Data')) {
                let assetsForRelate = this.model.get('assetsForRelate') || {};
                assetsForRelate[file.uniqueId] = response.getResponseHeader('X-Status-Reason-Data');
                this.model.set('assetsForRelate', assetsForRelate, {silent: true});
            }

            if (response.status !== 400) {
                html += ` <a href="javascript:" class="retry-upload" data-unique="${file.uniqueId}">${this.translate('retry', 'labels', 'Asset')}</a>`;
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
                $progress.css('width', percentCompleted + '%').html('&nbsp;' + percentCompleted + '% ' + this.translate('uploaded', 'labels', 'Asset'));
            } else {
                $progress.parent().hide();
            }
        },

        findFile: function (uniqueId) {
            let result = null;
            this.fileList.forEach(function (item) {
                if (item.uniqueId === uniqueId) {
                    result = item;
                }
            });

            return result;
        },

        pushPieceSize: function (hash, size) {
            if (this.isFileInList(hash)) {
                this.uploadedSize[hash].push(size);
                return true;
            }

            return false;
        },

        isFileInList: function (hash) {
            return typeof this.uploadedSize[hash] !== 'undefined'
        },

    })
});