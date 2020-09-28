

Espo.define('views/admin/dynamic-logic/conditions-string/group-not', 'views/admin/dynamic-logic/conditions-string/group-base', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/conditions-string/group-not',

        data: function () {
            return {
                viewKey: this.viewKey,
                operator: this.operator
            };
        },

        setup: function () {
            this.level = this.options.level || 0;
            this.number = this.options.number || 0;
            this.scope = this.options.scope;

            this.operator = this.options.operator || this.operator;

            this.itemData = this.options.itemData || {};
            this.viewList = [];


            var i = 0;
            var key = 'view-' + this.level.toString() + '-' + this.number.toString() + '-' + i.toString();

            this.createItemView(i, key, this.itemData.value);
            this.viewKey = key;
        }

    });

});

