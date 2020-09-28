

Espo.define('views/user/modals/access', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'user-access',

        multiple: false,

        template: 'user/modals/access',

        header: false,

        data: function () {
            return {
                valuePermissionDataList: this.getValuePermissionList(),
                levelListTranslation: this.getLanguage().get('Role', 'options', 'levelList') || {}
            };
        },

        getValuePermissionList: function () {
            var list = this.getMetadata().get(['app', 'acl', 'valuePermissionList'], []);
            var dataList = [];
            list.forEach(function (item) {
                var o = {};
                o.name = item;
                o.value = this.options.aclData[item];
                dataList.push(o);
            }, this);
            return dataList;
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            var fieldTable = Espo.Utils.cloneDeep(this.options.aclData.fieldTable || {});
            for (var scope in fieldTable) {
                var scopeData = fieldTable[scope] || {};
                for (var field in scopeData) {
                    if (this.getMetadata().get(['app', 'acl', 'mandatory', 'scopeFieldLevel', scope, field]) !== null) {
                        delete scopeData[field];
                    }

                    if (this.getMetadata().get(['entityDefs', scope, 'fields', field, 'readOnly'])) {
                        if (scopeData[field].edit === 'no' && scopeData[field].read === 'yes') {
                            delete scopeData[field];
                        }
                    }
                }
            }

            this.createView('table', 'views/role/record/table', {
                acl: {
                    data: this.options.aclData.table,
                    fieldData: fieldTable,
                },
                final: true
            });

            this.header = this.translate('Access');
        }

    });
});

