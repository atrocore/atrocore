

Espo.define('views/import/record/row-actions/duplicates', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            var list = Dep.prototype.getActionList.call(this);

            list.push({
                action: 'unmarkAsDuplicate',
                label: 'Set as Not Duplicate',
                data: {
                    id: this.model.id,
                    type: this.model.name
                }
            });

            return list;
        }

    });

});


