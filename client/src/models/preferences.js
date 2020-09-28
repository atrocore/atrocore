
Espo.define('models/preferences', 'model', function (Dep) {

    return Dep.extend({

        name: "Preferences",

        settings: null,

        getDashletOptions: function (id) {
            var value = this.get('dashletsOptions') || {};
            return value[id] || false;
        }

    });

});
