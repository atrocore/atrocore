

Espo.define('views/user/list', 'views/list', function (Dep) {

    return Dep.extend({

        storeViewAfterUpdate: false,

        setup: function () {
            Dep.prototype.setup.call(this);
        }

    });
});

