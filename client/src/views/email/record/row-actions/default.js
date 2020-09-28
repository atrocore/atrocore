

Espo.define('views/email/record/row-actions/default', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change', function (model) {
                if (model.hasChanged('isImportant') || model.hasChanged('inTrash')) {
                    setTimeout(function () {
                        this.reRender();
                    }.bind(this), 10);
                }
            }, this);
        },

        getActionList: function () {
            var list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                }
            }];
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'quickEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        }
                    }
                ]);
            }
            if (this.model.get('isUsers') && this.model.get('status') !== 'Draft') {
                if (!this.model.get('inTrash')) {
                    list.push({
                        action: 'moveToTrash',
                        label: 'Move to Trash',
                        data: {
                            id: this.model.id
                        }
                    });
                } else {
                    list.push({
                        action: 'retrieveFromTrash',
                        label: 'Retrieve from Trash',
                        data: {
                            id: this.model.id
                        }
                    });
                }


            }
            if (this.options.acl.delete) {
                list.push({
                    action: 'quickRemove',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }
            if (this.model.get('isUsers')) {
                if (!this.model.get('isImportant')) {
                    if (!this.model.get('inTrash')) {
                        list.push({
                            action: 'markAsImportant',
                            label: 'Mark as Important',
                            data: {
                                id: this.model.id
                            }
                        });
                    }
                } else {
                    list.push({
                        action: 'markAsNotImportant',
                        label: 'Unmark Importance',
                        data: {
                            id: this.model.id
                        }
                    });
                }
            }
            if (this.model.get('isUsers') && this.model.get('status') !== 'Draft') {
                if (!this.model.get('inTrash')) {
                    list.push({
                        action: 'moveToFolder',
                        label: 'Move to Folder',
                        data: {
                            id: this.model.id
                        }
                    });
                }
            }
            return list;
        }

    });

});


