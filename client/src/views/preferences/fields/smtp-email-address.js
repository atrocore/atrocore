

Espo.define('views/preferences/fields/smtp-email-address', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        detailTemplate: 'preferences/fields/smtp-email-address/detail',

        data: function () {
            return _.extend({
                isAdmin: this.getUser().isAdmin()
            }, Dep.prototype.data.call(this));
        },

    });

});
