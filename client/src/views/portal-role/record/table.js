

Espo.define('views/portal-role/record/table', 'views/role/record/table', function (Dep) {

    return Dep.extend({

        levelListMap: {
            'recordAllAccountContactOwnNo': ['all', 'account', 'contact', 'own', 'no'],
            'recordAllAccountOwnNo': ['all', 'account', 'own', 'no'],
            'recordAllContactOwnNo': ['all', 'contact', 'own', 'no'],
            'recordAllAccountNo': ['all', 'account', 'no'],
            'recordAllContactNo': ['all', 'contact', 'no'],
            'recordAllAccountContactNo': ['all', 'account', 'contact', 'no'],
            'recordAllOwnNo': ['all', 'own', 'no'],
            'recordAllNo': ['all', 'no'],
            'record': ['all', 'own', 'no']
        },

        levelList: ['all', 'account', 'contact', 'own', 'no'],

        type: 'aclPortal',

        setupScopeList: function () {
            this.aclTypeMap = {};
            this.scopeList = [];

            var scopeListAll = Object.keys(this.getMetadata().get('scopes')).sort(function (v1, v2) {
                 return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            scopeListAll.forEach(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled') || this.getMetadata().get('scopes.' + scope + '.disabledPortal')) return;
                var acl = this.getMetadata().get('scopes.' + scope + '.aclPortal');
                if (acl) {
                    this.scopeList.push(scope);
                    this.aclTypeMap[scope] = acl;
                    if (acl === true) {
                        this.aclTypeMap[scope] = 'record';
                    }
                }
            }, this);
        }

    });
});


