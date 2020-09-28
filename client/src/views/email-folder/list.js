

Espo.define('views/email-folder/list', 'views/list', function (Dep) {

    return Dep.extend({

        quickCreate: true,

        setup: function () {
            Dep.prototype.setup.call(this);
            this.collection.data = {
                boolFilterList: ['onlyMy']
            };
        },

    });
});

