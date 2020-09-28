

Espo.define('views/admin/auth-token/record/row-actions/default', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:isActive', function () {
                setTimeout(function () {
                    this.reRender();
                }.bind(this), 10);
            }, this);
        },

        getActionList: function () {
            var list = [];

                list.push({
                    action: 'quickView',
                    label: 'View',
                    data: {
                        id: this.model.id
                    }
                });

            if (this.model.get('isActive')) {
                list.push({
                    action: 'setInactive',
                    label: 'Set Inactive',
                    data: {
                        id: this.model.id
                    }
                });
            }
            list.push({
                action: 'quickRemove',
                label: 'Remove',
                data: {
                    id: this.model.id
                }
            });
            return list;
        }
    });

});


