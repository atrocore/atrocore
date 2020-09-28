

Espo.define('views/stream/notes/assign', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/assign',

        messageName: 'assign',

        data: function () {
            return _.extend({
            }, Dep.prototype.data.call(this));
        },

        init: function () {
            if (this.getUser().isAdmin()) {
                this.isRemovable = true;
            }
            Dep.prototype.init.call(this);
        },

        setup: function () {
            var data = this.model.get('data');

            this.assignedUserId = data.assignedUserId || null;
            this.assignedUserName = data.assignedUserName || null;

            this.messageData['assignee'] = '<a href="#User/view/' + data.assignedUserId + '">' + data.assignedUserName + '</a>';

            if (this.isUserStream) {
                if (this.assignedUserId) {
                    if (this.assignedUserId == this.model.get('createdById')) {
                        this.messageName += 'Self';
                    } else {
                        if (this.assignedUserId == this.getUser().id) {
                            this.messageName += 'You';
                        }
                    }
                } else {
                    this.messageName += 'Void';
                }
            } else {
                if (this.assignedUserId) {
                    if (this.assignedUserId == this.model.get('createdById')) {
                        this.messageName += 'Self';
                    }
                } else {
                    this.messageName += 'Void';
                }
            }

            this.createMessage();
        },

    });
});

