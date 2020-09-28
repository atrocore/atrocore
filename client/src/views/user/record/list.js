

Espo.define('views/user/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        quickEditDisabled: true,

        massActionList: ['remove', 'massUpdate', 'export'],

        checkAllResultMassActionList: ['massUpdate', 'export'],

        setupMassActionItems: function () {
            Dep.prototype.setupMassActionItems.call(this);
            if (!this.getUser().isAdmin()) {
                this.removeMassAction('massUpdate');
                this.removeMassAction('export');
            }
        },

        getModelScope: function (id) {
            var model = this.collection.get(id);

            if (model.get('isPortalUser')) {
                return 'PortalUser';
            }
            return this.scope;
        }
    });

});

