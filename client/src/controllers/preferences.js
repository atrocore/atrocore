

Espo.define('controllers/preferences', ['controllers/record', 'models/preferences'], function (Dep, Preferences) {

    return Dep.extend({

        defaultAction: 'own',

        getModel: function (callback) {
            var model = new Preferences();
            model.settings = this.getConfig();
            model.defs = this.getMetadata().get('entityDefs.Preferences');
            callback.call(this, model);
        },

        checkAccess: function (action) {
            return true;
        },

        own: function () {
            this.edit({
                id: this.getUser().id
            });
        },

        list: function () {}
    });
});


