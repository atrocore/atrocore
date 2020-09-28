

Espo.define('views/stream/notes/create', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/create',

        assigned: false,

        messageName: 'create',

        isRemovable: false,

        data: function () {
            return _.extend({
                statusText: this.statusText,
                statusStyle: this.statusStyle
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            if (this.model.get('data')) {
                var data = this.model.get('data');

                this.assignedUserId = data.assignedUserId || null;
                this.assignedUserName = data.assignedUserName || null;

                this.messageData['assignee'] = '<a href="#User/view/' + this.assignedUserId + '">' + this.assignedUserName + '</a>';

                var isYou = false;
                if (this.isUserStream) {
                    if (this.assignedUserId == this.getUser().id) {
                        isYou = true;
                    }
                }

                if (this.assignedUserId) {
                    this.messageName = 'createAssigned';

                    if (this.isThis) {
                        this.messageName += 'This';

                        if (this.assignedUserId == this.model.get('createdById')) {
                            this.messageName += 'Self';
                        }
                    } else {
                        if (this.assignedUserId == this.model.get('createdById')) {
                            this.messageName += 'Self';
                        } else {
                            if (isYou) {
                                this.messageName += 'You';
                            }
                        }
                    }
                }

                if (data.statusField) {
                    var statusField = this.statusField = data.statusField;
                    var statusValue = data.statusValue;
                    this.statusStyle = data.statusStyle || 'default';
                    this.statusText = this.getLanguage().translateOption(statusValue, statusField, this.model.get('parentType'));
                }
            }

            this.createMessage();
        },
    });
});

