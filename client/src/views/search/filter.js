

Espo.define('views/search/filter', 'view', function (Dep) {

    return Dep.extend({

        template: 'search/filter',

        data: function () {
            return {
                name: this.name,
                scope: this.model.name,
                notRemovable: this.options.notRemovable
            };
        },

        setup: function () {
            var name = this.name = this.options.name;
            var type = this.model.getFieldType(name);

            if (type) {
                var viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

                this.createView('field', viewName, {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    defs: {
                        name: name,
                    },
                    searchParams: this.options.params,
                });
            }
        },

        populateDefaults: function () {
            var view = this.getView('field');
            if (!view) return;
            if (!('populateSearchDefaults' in view)) return;
            view.populateSearchDefaults();
        }
    });
});

