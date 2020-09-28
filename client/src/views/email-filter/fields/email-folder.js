
Espo.define('views/email-filter/fields/email-folder', 'views/fields/link', function (Dep) {

    return Dep.extend({

        createDisabled: true,

        autocompleteDisabled: true,

        getSelectFilters: function () {
            if (this.getUser().isAdmin()) {
                if (this.model.get('parentType') === 'User' && this.model.get('parentId')) {
                    return {
                        assignedUser: {
                            type: 'equals',
                            field: 'assignedUserId',
                            value: this.model.get('parentId'),
                            valueName: this.model.get('parentName')
                        }
                    };
                }
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:parentId', function (model, e, data) {
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
