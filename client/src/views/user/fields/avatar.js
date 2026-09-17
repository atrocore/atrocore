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

Espo.define('views/user/fields/avatar', 'views/fields/file', function (Dep) {

    return Dep.extend({

        readOnly: true,

        isOwnAvatar: function () {
            return this.model.id === this.getUser().id;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.isOwnAvatar()) {
                this.on('after:render', function () {
                    this.initAvatarActions();
                }, this);
            }
        },

        initAvatarActions: function () {
            var $cell = this.getCellElement();
            var $inlineActions = this.getInlineActionsContainer();

            $inlineActions.find('.avatar-upload-link, .avatar-delete-link').remove();

            var $uploadLink = $('<a href="javascript:" class="avatar-upload-link hidden" title="' + this.translate('Upload') + '"><i class="ph ph-paperclip"></i></a>');
            var $deleteLink = $('<a href="javascript:" class="avatar-delete-link hidden" title="' + this.translate('Delete') + '"><i class="ph ph-trash"></i></a>');

            if ($inlineActions.size()) {
                $inlineActions.prepend($uploadLink, $deleteLink);
            } else {
                $cell.prepend($uploadLink, $deleteLink);
            }

            $uploadLink.on('click', function () {
                this.uploadAvatar();
            }.bind(this));

            $deleteLink.on('click', function () {
                this.deleteAvatar();
            }.bind(this));

            $cell.off('mouseenter.avatarActions mouseleave.avatarActions').on('mouseenter.avatarActions', function (e) {
                e.stopPropagation();
                if (this.disabled) {
                    return;
                }
                $uploadLink.removeClass('hidden');
                $deleteLink.removeClass('hidden');
            }.bind(this)).on('mouseleave.avatarActions', function (e) {
                e.stopPropagation();
                $uploadLink.addClass('hidden');
                $deleteLink.addClass('hidden');
            });
        },

        uploadAvatar: function () {
            var $input = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp">');

            $input.one('change', function () {
                var file = $input[0].files[0];
                if (!file) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (e) {
                    this.createView('crop', 'views/modals/image-crop', {
                        contents: e.target.result
                    }, function (view) {
                        view.render();

                        this.listenToOnce(view, 'crop', function (croppedContents) {
                            this.clearView('crop');
                            this.uploadAvatarFile(croppedContents, file.name, this.getDataUrlSize(croppedContents));
                        }.bind(this));

                        this.listenToOnce(view, 'remove', function () {
                            this.clearView('crop');
                        }.bind(this));
                    }.bind(this));
                }.bind(this);

                reader.readAsDataURL(file);
            }.bind(this));

            $input.trigger('click');
        },

        // byte size of the base64-decoded payload, computed without actually decoding it
        getDataUrlSize: function (dataUrl) {
            var base64 = (dataUrl.split(',')[1] || '').replace(/\s/g, '');
            var padding = (base64.match(/=+$/) || [''])[0].length;

            return Math.floor((base64.length * 3) / 4) - padding;
        },

        uploadAvatarFile: function (dataUrl, filename, size) {
            this.notify('Loading...');

            $.ajax({
                type: 'POST',
                url: 'User/avatar',
                contentType: 'application/json',
                data: JSON.stringify({fileContents: dataUrl, name: filename, filesize: size})
            }).done(function () {
                this.model.fetch().done(function () {
                    this.notify(false);
                    this.reRender();
                }.bind(this));
            }.bind(this)).fail(function () {
                this.notify(this.translate('Error occurred'), 'error');
            }.bind(this));
        },

        deleteAvatar: function () {
            this.notify('Loading...');

            $.ajax({
                type: 'DELETE',
                url: 'User/avatar'
            }).done(function () {
                this.model.fetch().done(function () {
                    this.notify(false);
                    this.reRender();
                }.bind(this));
            }.bind(this)).fail(function () {
                this.notify(this.translate('Error occurred'), 'error');
            }.bind(this));
        },

        getValueForDisplay: function () {
            if (this.mode === 'detail' || this.mode === 'list') {
                let userId = this.model.id,
                    t = this.getCache() ? this.getCache().get('app', 'timestamp') : Date.now(),
                    imgHtml = null;

                if (this.mode === 'detail') {
                    imgHtml = '<img style="width:100%;height:auto;" src="'+this.getBasePath()+'?entryPoint=avatar&size=' + this.previewSize + '&id=' + userId + '&time=' + t + '">';
                } else {
                    imgHtml = '<img width="16" src="'+this.getBasePath()+'?entryPoint=avatar&size=' + this.previewSize + '&id=' + userId + '&time=' + t + '">';
                }

                return '<a data-action="showImagePreview" data-id="' + userId + '" style="display:block;width:100%;" href="' + this.getBasePath() + '?entryPoint=avatar&id=' + userId + '">' + imgHtml + '</a>';
            }
        },

        prepareMediaFromModel: function (model) {
            let userId = model.id,
                t = this.getCache() ? this.getCache().get('app', 'timestamp') : Date.now(),
                baseUrl = this.getBasePath() + '?entryPoint=avatar&id=' + userId + '&time=' + t;

            return {
                id: userId,
                name: model.get('name'),
                url: baseUrl,
                isImage: true
            };
        },

    });

});