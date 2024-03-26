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

        previewSize: 'small',

        events: {
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
            }
        },

        data: function () {
            return _.extend({valueIsSet: this.model.has(this.idName)}, Dep.prototype.data.call(this));
        },

        setup: function () {
            this.nameName = this.name + 'Name';
            this.namePathsData = this.name + 'PathsData';
            this.idName = this.name + 'Id';
            this.foreignScope = 'File';

            this.previewSize = this.options.previewSize || this.params.previewSize || this.previewSize;

            if (this.mode !== 'list') {
                this.addActionHandler('selectLink', function () {
                    this.selectLink();
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

        handleResize: function () {
            var width = this.$el.width();
            this.$el.find('img.image-preview').css('maxWidth', width + 'px');
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

    });
});
