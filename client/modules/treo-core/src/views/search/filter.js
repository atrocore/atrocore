

Espo.define('treo-core:views/search/filter', 'views/search/filter', function (Dep) {

    return Dep.extend({

        template: 'treo-core:search/filter',

        data: function () {
            return {
                generalName: this.generalName,
                name: this.name,
                scope: this.model.name,
                notRemovable: this.options.notRemovable
            };
        },

        setup: function () {
            var newName = this.name = this.options.name;
            this.generalName = newName.split('-')[0];
            var type = this.model.getFieldType(this.generalName);

            if (type) {
                var viewName = this.model.getFieldParam(this.generalName, 'view') || this.getFieldManager().getViewName(type);

                this.createView('field', viewName, {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    defs: {
                        name: this.generalName,
                    },
                    searchParams: this.options.params,
                });
            }
        }
    });
});