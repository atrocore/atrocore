

Espo.define('views/admin/dynamic-logic/conditions-string/item-operator-only-date', 'views/admin/dynamic-logic/conditions-string/item-operator-only-base', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions-string/item-operator-only-date',

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.dateValue = this.dateValue;
            return data;
        },

    });

});

