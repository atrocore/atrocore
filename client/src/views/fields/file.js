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

Espo.define('views/fields/file', 'views/fields/link', function (Dep) {

    return Dep.extend({

        type: 'file',

        listTemplate: 'fields/file/list',

        detailTemplate: 'fields/file/detail',

        editTemplate: 'fields/file/edit',

        previewSize: 'small',

        fileTypeId: null,

        uploadDisabled: false,

        disableLink: false,

        accept: false,

        shouldAvoidAutomaticalExtensionUpdate : false,

        events: {
            'click a[data-action="showImagePreview"]': function (e) {
                e.preventDefault();

                let items;
                let canLoadMore = false;
                let modalContainer = this;
                let collection = null;
                if (this.model.collection && this.model.collection.models && this.model.collection.name === 'File') {
                    collection = this.model.collection;
                    items = collection.models
                        .filter(item => this.hasPreview(item.get(this.nameName)))
                        .map(item => this.prepareMediaFromModel(item));

                    if (collection.length < collection.total) {
                        canLoadMore = true;
                        modalContainer = this.getParentView().getParentView();
                    }

                } else {
                    items = [this.prepareMediaFromModel(this.model)];
                }

                modalContainer.createView('gallery', 'views/modals/gallery', {
                    id: this.model.get(this.idName),
                    model: this.model,
                    mediaList: items,
                    canLoadMore: canLoadMore
                }, view => {
                    view.render();

                    view.on('gallery:load-more', () => {
                        collection.fetch({
                            more: true,
                            remove: false,
                            success: () => {
                                view.trigger('gallery:load-more:success', {
                                    mediaList: collection.models
                                        .filter(item => this.hasPreview(item.get(this.nameName)))
                                        .map(item => this.prepareMediaFromModel(item)),
                                    canLoadMore: collection.length < collection.total,
                                });
                            }
                        });
                    });
                });
            },

            'click a[data-action="showVideoPreview"]': function (e) {
                e.preventDefault();

                var id = this.model.get(this.idName);
                this.createView('video-preview', 'views/modals/video-preview', {
                    id: id,
                    model: this.model,
                    name: this.model.get(this.nameName),
                    fileId: this.model.get(this.idName),
                    url: this.getDownloadUrl(id)
                }, function (view) {
                    view.render();
                });
            }
        },

        selectBoolFilterList: ['onlyType'],

        boolFilterData: {
            onlyType() {
                return this.fileTypeId || null;
            }
        },

        data: function () {
            return _.extend({
                uploadDisabled: this.uploadDisabled,
                valueIsSet: this.model.has(this.idName),
                accept: this.accept
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.nameName = this.name + 'Name';
            this.namePathsData = this.name + 'PathsData';
            this.idName = this.name + 'Id';
            this.foreignScope = 'File';
            this.fileTypeId = this.options.fileTypeId || this.params.fileTypeId || this.model.getFieldParam(this.name, 'fileTypeId') || this.fileTypeId;
            this.previewSize = this.options.previewSize || this.params.previewSize || this.model.getFieldParam(this.name, 'previewSize') || this.previewSize;

            if (typeof this.options.disableLink === 'boolean') {
                this.disableLink = this.options.disableLink;
            } else if (typeof this.model.getFieldParam(this.name, 'disableLink') === 'boolean') {
                this.disableLink = this.model.getFieldParam(this.name, 'disableLink');
            }

            if ('uploadDisabled' in this.options) {
                this.uploadDisabled = this.options.uploadDisabled;
            }

            if (!this.uploadDisabled) {
                if (!this.getAcl().check(this.foreignScope, 'create')) {
                    this.uploadDisabled = true;
                }
            }

            if (this.mode !== 'list') {
                this.addActionHandler('selectLink', function () {
                    this.selectLink();
                });

                this.addActionHandler('uploadLink', function () {
                    this.uploadLink();
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

        afterRender: function () {
            if (this.mode === 'edit' || this.mode === 'search') {
                Dep.prototype.afterRender.call(this);
            } else if (this.mode === 'detail') {
                if (this.previewSize === 'large') {
                    this.handleResize();
                    this.resizeIsBeingListened = true;
                    $(window).on('resize.' + this.cid, function () {
                        this.handleResize();
                    }.bind(this));
                }
            }
        },

        onInlineEditSave(res, attrs, model){
            model.set(this.namePathsData, res[this.namePathsData] || null);

            Dep.prototype.onInlineEditSave.call(this, res, attrs, model);
        },

        handleResize: function () {
            var width = this.$el.width();
            this.$el.find('img.image-preview').css('maxWidth', width + 'px');
        },

        hasPreview: function (name) {
            const hasPreviewExtensions = this.getMetadata().get('app.file.image.hasPreviewExtensions') || [];
            return $.inArray(this.getFileExtension(name), hasPreviewExtensions) !== -1;
        },

        hasVideoPlayer: function (name) {
            return (this.getMetadata().get('app.file.video.hasVideoPlayerExtensions') || []).includes(this.getFileExtension(name));
        },

        isParentNoteUpdatedType: function () {
            const parentView = this.getParentView();
            return parentView && (parentView.messageName ?? null) === 'updateFromToThis';
        },

        getValueForDisplay: function () {
            if (this.mode === 'detail' || this.mode === 'list') {
                let id = this.model.get(this.idName);
                let name = this.model.get(this.nameName);

                if (!id) {
                    return false;
                }

                let html = '';
                if (this.hasPreview(name) && this.getImageUrl(id, this.previewSize)) {
                    html += '<div class="attachment-preview"><a data-action="showImagePreview" data-id="' + id + '" href="' + this.getImageUrl(id) + '"><img src="' + this.getImageUrl(id, this.previewSize) + '" class="image-preview"></a></div>';

                    if (this.disableLink) {
                        return html;
                    }
                } else if (this.hasVideoPlayer(name) && !this.isParentNoteUpdatedType()) {
                    html += `<div class="attachment-preview"><a data-action="showVideoPreview" href="${this.getDownloadUrl(id)}"><span class="fiv-cla fiv-icon-${this.getFileExtension(name)} fiv-size-lg"></span></a></div>`;
                }
                html += '<div class="file-link"><a href="' + this.getDownloadUrl(id) + '" download="" title="' + this.translate('Download') + '"> <i class="ph ph-download-simple"></i></a> <a href="/#File/view/' + id + '" title="' + name + '">' + Handlebars.Utils.escapeExpression(name) + '</a></div>';

                return html;
            }
        },

        getFilePathsData: function (model) {
            if (model) {
                return model.get(this.namePathsData);
            }

            return this.model.get(this.namePathsData);
        },

        getFileExtension: function (name) {
            return (name || '').split('.').pop().toLowerCase();
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

            return path;
        },

        getDownloadUrl: function (id) {
            let data = this.getFilePathsData();
            if (!data) {
                return this.getBasePath() + '?entryPoint=download&id=' + id;
            }

            return data['download'];
        },

        selectLink: function () {
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
            }, view => {
                view.render();
                this.notify(false);
                this.listenTo(view, 'select', model => this.select(model));
                this.listenTo(view, 'unselect', () => this.clearLink());
            });
        },

        uploadLink: function () {
            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                fullFormDisabled: true,
                layoutName: 'upload',
                multiUpload: false,
                attributes: this.getCreateAttributes(),
            }, view => {
                view.render();
                this.notify(false);
                this.listenTo(view.model, 'after:file-upload', entity => {
                    this.afterFileUpload(view);
                    this.getModelFactory().create('File', model => {
                        model.set(entity);
                        this.select(model);
                    });
                });
                this.listenTo(view.model, 'after:delete-action', id => this.clearLink());

                this.listenToOnce(view, 'close', () => {
                    this.clearView('upload');
                });
            });
        },

        afterFileUpload(view) {
        },

        getCreateAttributes: function () {
            let res = {
                accept: this.accept,
                shouldAvoidAutomaticalExtensionUpdate : this.shouldAvoidAutomaticalExtensionUpdate 
            };

            if (this.fileTypeId) {
                this.ajaxGetRequest(`FileType/${this.fileTypeId}`, null, {async: false}).success(entity => {
                    res.typeId = entity.id;
                    res.typeName = entity.name;
                });
            }

            if (this.getMetadata().get(['scopes', 'File', 'hasOwner'])) {
                res.ownerUserId = this.getUser().id;
                res.ownerUserName = this.getUser().get('name');
            }
            if (this.getMetadata().get(['scopes', 'File', 'hasAssignedUser'])) {
                res.assignedUserId = this.getUser().id;
                res.assignedUserName = this.getUser().get('name');
            }
            if (this.getMetadata().get(['scopes', 'File', 'hasTeam'])) {
                res.teamsIds = this.model.get('teamsIds') || null;
                res.teamsNames = this.model.get('teamsNames') || null;
            }

            return res;
        },

        prepareMediaFromModel: function (model) {
            let cachePart = model.get('fileMtime') ? '?time=' + (new Date(model.get('fileMtime'))).getTime() : '';

            return {
                id: model.get(this.idName),
                name: model.get(this.nameName),
                url: this.getFilePathsData(model).download,
                smallThumbnail: this.getFilePathsData(model).thumbnails.small + cachePart,
                largeThumbnail: this.getFilePathsData(model).thumbnails.large + cachePart,
                isImage: model.get('typeId') !== 'a_document',
            };
        }

    });
});
