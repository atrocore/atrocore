

Espo.define('views/email-folder/record/row-actions/default', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        getActionList: function () {
            var list = Dep.prototype.getActionList.call(this);
            if (this.options.acl.edit) {
                list.unshift({
                    action: 'moveDown',
                    label: 'Move Down',
                    data: {
                        id: this.model.id
                    }
                });
                list.unshift({
                    action: 'moveUp',
                    label: 'Move Up',
                    data: {
                        id: this.model.id
                    }
                });
            }
            return list;
        }

    });

});


