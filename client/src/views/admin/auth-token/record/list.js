

Espo.define('views/admin/auth-token/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        rowActionsView: 'views/admin/auth-token/record/row-actions/default',

        massActionList: ['remove', 'setInactive'],

        checkAllResultMassActionList: ['remove', 'setInactive'],

        massActionSetInactive: function () {
            var ids = false;
            var allResultIsChecked = this.allResultIsChecked;
            if (!allResultIsChecked) {
                ids = this.checkedList;
            }
            var attributes = {
                isActive: false
            };

            var ids = false;
            var allResultIsChecked = this.allResultIsChecked;
            if (!allResultIsChecked) {
                ids = this.checkedList;
            }

            this.ajaxPutRequest(this.scope + '/action/massUpdate', {
                attributes: attributes,
                ids: ids || null,
                where: (!ids || ids.length == 0) ? this.collection.getWhere() : null,
                selectData: (!ids || ids.length == 0) ? this.collection.data : null,
                byWhere: this.allResultIsChecked
            }).then(function () {
                var result = result || {};
                var count = result.count;
                this.collection.fetch();
            }.bind(this));
        },

        actionSetInactive: function (data) {
            if (!data.id) return;
            var model = this.collection.get(data.id);

            if (!model) return;

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            model.save({
                'isActive': false
            }, {patch: true}).then(function () {
                Espo.Ui.notify(false);
            });
        }

    });
});

