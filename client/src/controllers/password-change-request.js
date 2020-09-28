

Espo.define('controllers/password-change-request', 'controller', function (Dep) {

    return Dep.extend({

        passwordChange: function (id) {
            if (!id) {
                throw new Error();
            }

            this.entire('views/user/password-change-request', {
                requestId: id
            }, function (view) {
                view.render();
            });
        },
    });
});

