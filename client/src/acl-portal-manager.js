



Espo.define('acl-portal-manager', ['acl-manager', 'acl-portal'], function (Dep, AclPortal) {

    return Dep.extend({

        checkInAccount: function (model) {
            return this.getImplementation(model.name).checkInAccount(model);
        },

        checkIsOwnContact: function (model) {
            return this.getImplementation(model.name).checkIsOwnContact(model);
        },

        getImplementation: function (scope) {
            if (!(scope in this.implementationHash)) {
                var implementationClass = AclPortal;
                if (scope in this.implementationClassMap) {
                    implementationClass = this.implementationClassMap[scope];
                }
                var obj = new implementationClass(this.getUser(), scope, this.aclAllowDeleteCreated);
                this.implementationHash[scope] = obj;
            }
            return this.implementationHash[scope];
        },

    });

});

