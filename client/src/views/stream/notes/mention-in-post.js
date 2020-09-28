

Espo.define('views/stream/notes/mention-in-post', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/post',

        messageName: 'mentionInPost',

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.showAttachments = !!(this.model.get('attachmentsIds') || []).length;
            data.showPost = !!this.model.get('post');
            return data;
        },

        setup: function () {
            if (this.model.get('post')) {
                this.createField('post', null, null, 'views/stream/fields/post');
            }
            if ((this.model.get('attachmentsIds') || []).length) {
                this.createField('attachments', 'attachmentMultiple', {}, 'views/stream/fields/attachment-multiple', {
                    previewSize: this.options.isNotification ? 'small' : null
                });
            }

            var data = this.model.get('data');

            this.messageData['mentioned'] = this.options.userId;

            if (!this.model.get('parentId')) {
                this.messageName = 'mentionInPostTarget';
            }

            if (this.isUserStream) {
                if (this.options.userId == this.getUser().id) {
                    if (!this.model.get('parentId')) {
                        this.messageName = 'mentionYouInPostTarget';
                        if (this.model.get('isGlobal')) {
                            this.messageName = 'mentionYouInPostTargetAll';
                        } else {
                            this.messageName = 'mentionYouInPostTarget';
                            if (this.model.has('teamsIds') && this.model.get('teamsIds').length) {
                                var teamIdList = this.model.get('teamsIds');
                                var teamNameHash = this.model.get('teamsNames') || {};

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

                                if (userIdList.length === 1 && userIdList[0] === this.model.get('createdById')) {
                                    this.messageName = 'mentionYouInPostTargetNoTarget';
                                } else {
                                    var userHtml = '';
                                    var userHtmlList = [];
                                    userIdList.forEach(function (userId) {
                                        var userName = userNameHash[userId];
                                        if (userName) {
                                            userHtmlList.push('<a href="#User/view/' + userId + '">' + userName + '</a>');
                                        }
                                    }, this);
                                    this.messageData['target'] = userHtmlList.join(', ');
                                }
                            } else if (this.model.get('targetType') === 'self') {
                                this.messageName = 'mentionYouInPostTargetNoTarget';
                            }
                        }
                    } else {
                        this.messageName = 'mentionYouInPost';
                    }
                }
            }

            this.createMessage();
        }

    });
});

