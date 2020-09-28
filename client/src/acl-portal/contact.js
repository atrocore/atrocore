

Espo.define('acl-portal/contact', 'acl-portal', function (Dep) {

    return Dep.extend({

        checkIsOwnContact: function (model) {
            var contactId = this.getUser().get('contactId');
            if (!contactId) {
                return false;
            }
            if (contactId === model.id) {
                return true;
            }
            return false;
        }

    });

});

