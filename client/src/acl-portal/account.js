

Espo.define('acl-portal/account', 'acl-portal', function (Dep) {

    return Dep.extend({

        checkInAccount: function (model) {
            var accountIdList = this.getUser().getLinkMultipleIdList('accounts');

            if (!accountIdList.length) {
                return false;
            }

            if (~accountIdList.indexOf(model.id)) {
                return true;
            }
            return false;
        }

    });

});

