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

Espo.define('views/stream/notes/post', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/post',

        messageName: 'post',

        isEditable: true,

        isRemovable: true,

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.showAttachments = !!(this.model.get('attachmentsIds') || []).length;
            data.showPost = !!this.model.get('post');
            return data;
        },

        setup: function () {

            this.createField('post', null, null, 'views/stream/fields/post');
            this.createField('attachments', 'attachmentMultiple', {}, 'views/stream/fields/attachment-multiple', {
                previewSize: this.options.isNotification ? 'small' : 'medium'
            });

            if (!this.model.get('post') && this.model.get('parentId')) {
                this.messageName = 'attach';
                if (this.isThis) {
                    this.messageName += 'This';
                }
            }

            this.listenTo(this.model, 'change', function () {
                if (this.model.hasChanged('post') || this.model.hasChanged('attachmentsIds')) {
                    this.reRender();
                }
            }, this);

            if (!this.model.get('parentId')) {
                if (this.model.get('isGlobal')) {
                    this.messageName = 'postTargetAll';
                } else {
                    if (this.model.has('teamsIds') && this.model.get('teamsIds').length) {
                        var teamIdList = this.model.get('teamsIds');
                        var teamNameHash = this.model.get('teamsNames') || {};
                        this.messageName = 'postTargetTeam';
                        if (teamIdList.length > 1) {
                            this.messageName = 'postTargetTeams';
                        }

                        var targetHtml = '';
                        var teamHtmlList = [];
                        teamIdList.forEach(function (teamId) {
                            var teamName = teamNameHash[teamId];
                            if (teamName) {
                                teamHtmlList.push('<a href="#Team/view/' + teamId + '">' + teamName + '</a>');
                            }
                        }, this);

                        this.messageData['target'] = teamHtmlList.join(', ');
                    } else if (this.model.has('usersIds') && this.model.get('usersIds').length) {
                        var userIdList = this.model.get('usersIds');
                        var userNameHash = this.model.get('usersNames') || {};

                        this.messageName = 'postTarget';

                        if (userIdList.length === 1 && userIdList[0] === this.model.get('createdById')) {
                            this.messageName = 'postTargetSelf';
                        } else {
                            var userHtml = '';
                            var userHtmlList = [];
                            userIdList.forEach(function (userId) {
                                if (userId === this.getUser().id) {
                                    this.messageName = 'postTargetYou';
                                    if (userIdList.length > 1) {
                                        if (userId === this.model.get('createdById')) {
                                            this.messageName = 'postTargetSelfAndOthers';
                                        } else {
                                            this.messageName = 'postTargetYouAndOthers';
                                        }
                                    }
                                } else {
                                    if (userId === this.model.get('createdById')) {
                                        this.messageName = 'postTargetSelfAndOthers';
                                    } else {
                                        var userName = userNameHash[userId];
                                        if (userName) {
                                            userHtmlList.push('<a href="#User/view/' + userId + '">' + userName + '</a>');
                                        }
                                    }
                                }
                            }, this);
                            this.messageData['target'] = userHtmlList.join(', ');
                        }
                    }
                }
            }

            this.createMessage();
        },
    });
});

