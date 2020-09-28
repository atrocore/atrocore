

Espo.define('views/admin/formula/modals/add-function', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'admin/formula/modals/add-function',

        fitHeight: true,

        events: {
            'click [data-action="add"]': function (e) {
                this.trigger('add', $(e.currentTarget).data('value'));
            }
        },

        data: function () {
            return {
                functionDataList: this.functionDataList
            };
        },

        setup: function () {
            this.header = this.translate('Function');

            this.functionDataList = this.getMetadata().get('app.formula.functionList') || [];
        }

    });
});

