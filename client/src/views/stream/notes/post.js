

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
            data.isInternal = this.isInternal;
            return data;
        },

        setup: function () {

            this.createField('post', null, null, 'views/stream/fields/post');
            this.createField('attachments', 'attachmentMultiple', {}, 'views/stream/fields/attachment-multiple', {
                previewSize: this.options.isNotification ? 'small' : 'medium'
            });

            this.isInternal = this.model.get('isInternal');

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
                    } else if (this.model.has('portalsIds') && this.model.get('portalsIds').length) {
                        var portalIdList = this.model.get('portalsIds');
                        var portalNameHash = this.model.get('portalsNames') || {};
                        this.messageName = 'postTargetPortal';
                        if (portalIdList.length > 1) {
                            this.messageName = 'postTargetPortals';
                        }

                        var targetHtml = '';
                        var portalHtmlList = [];
                        portalIdList.forEach(function (portalId) {
                            var portalName = portalNameHash[portalId];
                            if (portalName) {
                                portalHtmlList.push('<a href="#Portal/view/' + portalId + '">' + portalName + '</a>');
                            }
                        }, this);

                        this.messageData['target'] = portalHtmlList.join(', ');
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

