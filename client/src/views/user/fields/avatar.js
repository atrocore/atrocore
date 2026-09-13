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

        handleFileUpload: function (file, contents, callback) {

            this.createView('crop', 'views/modals/image-crop', {
                contents: contents
            }, function (view) {
                view.render();

                var croped = false;

                this.listenToOnce(view, 'crop', function (croppedContents, params) {
                    croped = true;
                    setTimeout(function () {
                        params = params || {};
                        params.name = 'avatar.jpg';
                        params.type = 'image/jpeg';

                        callback(croppedContents, params);
                    }.bind(this), 10);
                });
                this.listenToOnce(view, 'remove', function () {
                    if (!croped) {
                        setTimeout(function () {
                            this.render();
                        }.bind(this), 10);
                    }
                    this.clearView('crop');
                }.bind(this));
            }.bind(this));
        },

        isOwnAvatar: function () {
            return this.model.id === this.getUser().id;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.isOwnAvatar()) {
                // in addition to clearing the (legacy, File-based) link fields, also remove
                // an avatar uploaded via the raw avatar endpoint, which isn't tied to avatarId
                this.addActionHandler('clearLink', function () {
                    Dep.prototype.clearLink.call(this);

                    if (this.model.get('avatarFileName')) {
                        $.ajax({
                            type: 'DELETE',
                            url: 'User/avatar'
                        }).done(function () {
                            this.model.fetch().done(function () {
                                this.reRender();
                            }.bind(this));
                        }.bind(this));
                    }
                }.bind(this));
            }
        },

        // uploads directly to the raw, ACL-free avatar endpoint instead of the generic
        // base64 File-upload flow, and only for the logged-in user's own avatar
        uploadLink: function () {
            if (!this.isOwnAvatar()) {
                Dep.prototype.uploadLink.call(this);
                return;
            }

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
                            this.uploadAvatarFile(croppedContents);
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

        uploadAvatarFile: function (dataUrl) {
            this.notify('Loading...');

            var formData = new FormData();
            formData.append('file', this.dataUrlToBlob(dataUrl), 'avatar.jpg');

            $.ajax({
                type: 'POST',
                url: 'User/avatar',
                data: formData,
                processData: false,
                contentType: false
            }).done(function () {
                this.model.fetch().done(function () {
                    this.notify(false);
                    this.reRender();
                }.bind(this));
            }.bind(this)).fail(function () {
                this.notify(this.translate('Error occurred'), 'error');
            }.bind(this));
        },

        dataUrlToBlob: function (dataUrl) {
            var parts = dataUrl.split(',');
            var mime = parts[0].match(/:(.*?);/)[1];
            var binary = atob(parts[1]);
            var array = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                array[i] = binary.charCodeAt(i);
            }

            return new Blob([array], {type: mime});
        },

        getValueForDisplay: function () {
            if (this.mode == 'detail' || this.mode == 'list') {
                var id = this.model.get(this.idName);
                var userId = this.model.id;

                var t = this.model.get('modifiedAt') ? (new Date(this.model.get('modifiedAt'))).getTime() : Date.now();

                var imgHtml;

                if (this.mode == 'detail') {
                    imgHtml = '<img src="'+this.getBasePath()+'?entryPoint=avatar&size=' + this.previewSize + '&id=' + userId + '&attachmentId=' + ( id || 'false') + '&t=' + t + '">';
                } else {
                    var cache = this.getCache();
                    if (cache) {
                        t = cache.get('app', 'timestamp');
                    }
                    imgHtml = '<img width="16" src="'+this.getBasePath()+'?entryPoint=avatar&size=' + this.previewSize + '&id=' + userId + '&t=' + t + '">';
                    return imgHtml;
                }

                if (id) {
                    return '<a data-action="showImagePreview" data-id="' + id + '" href="'+this.getBasePath()+'?entryPoint=image&id=' + id + '">' + imgHtml +' </a>';
                } else {
                    return imgHtml;
                }
            }
        },

    });

});
