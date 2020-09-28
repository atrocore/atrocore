

Espo.define('views/global-search/scope-badge', 'view', function (Dep) {

    return Dep.extend({

        template: 'global-search/scope-badge',

        data: function () {
            return {
                label: this.translate(this.model.get('_scope'), 'scopeNames')
            };
        },

    });

});

