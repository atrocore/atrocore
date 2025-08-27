Espo.define('views/user/fields/password-updated-at', 'views/fields/datetime-with-user', function (Dep) {

    return Dep.extend({

        getUserField(){
            return 'passwordUpdatedBy'
        },

    });

});
