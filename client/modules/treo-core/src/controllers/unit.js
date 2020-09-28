

Espo.define('treo-core:controllers/unit', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: "view",

        view: function () {
            var model = this.getConfig().clone();
            model.defs = this.getConfig().defs;

            model.once('sync', () => {
                model.id = '1';
                this.main('views/settings/edit', {
                    model: model,
                    headerTemplate: 'treo-core:admin/settings/headers/unit',
                    recordView: 'treo-core:views/admin/unit'
                });
            });
            model.fetch();
        },
    });
});
