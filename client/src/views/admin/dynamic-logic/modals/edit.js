


Espo.define('views/admin/dynamic-logic/modals/edit', 'views/modal', function (Dep) {

    return Dep.extend({

        template: 'admin/dynamic-logic/modals/edit',

        data: function () {
            return {
            };
        },

        events: {

        },

        buttonList: [
            {
                name: 'apply',
                label: 'Apply',
                style: 'primary'
            },
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup: function () {
            this.conditionGroup = Espo.Utils.cloneDeep(this.options.conditionGroup || []);
            this.scope = this.options.scope;

            this.createView('conditionGroup', 'views/admin/dynamic-logic/conditions/and', {
                el: this.getSelector() + ' .top-group-container',
                itemData: {
                    value: this.conditionGroup
                },
                scope: this.options.scope
            });
        },

        actionApply: function () {
            var data = this.getView('conditionGroup').fetch();

            var conditionGroup = data.value;

            this.trigger('apply', conditionGroup);
            this.close();
        },
    });
});


