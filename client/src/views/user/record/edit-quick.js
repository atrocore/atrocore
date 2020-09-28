

Espo.define('views/user/record/edit-quick', ['views/record/edit-small', 'views/user/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        sideView: 'views/user/record/edit-side',

        setup: function () {
            Dep.prototype.setup.call(this);
            Detail.prototype.setupNonAdminFieldsAccess.call(this);
        }

    });

});
