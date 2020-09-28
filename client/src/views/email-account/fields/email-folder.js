
Espo.define('views/email-account/fields/email-folder', 'views/fields/link', function (Dep) {

    return Dep.extend({

        createDisabled: true,

        autocompleteDisabled: true,

        getSelectFilters: function () {
            if (this.getUser().isAdmin()) {
                if (this.model.get('assignedUserId')) {
                    return {
                        assignedUser: {
                            type: 'equals',
                            field: 'assignedUserId',
                            value: this.model.get('assignedUserId'),
                            valueName: this.model.get('assignedUserName')
                        }
                    };
                }
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:assignedUserId', function (model, e, data) {
                if (data.ui) {
                    this.model.set({
                        emailFolderId: null,
                        emailFolderName: null
                    });
                }
            }, this);
        }

    });

});
