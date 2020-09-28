


/** * Example:
 * Lead: {
 *   edit: 'own',
 *   read: 'team',
 *   delete: 'no',
 * }
 */

Espo.define('acl-manager', ['acl'], function (Acl) {

    var AclManager = function (user, implementationClassMap, aclAllowDeleteCreated) {
        this.setEmpty();

        this.user = user || null;
        this.implementationClassMap = implementationClassMap || {};
        this.aclAllowDeleteCreated = aclAllowDeleteCreated;
    }

    _.extend(AclManager.prototype, {

        data: null,

        user: null,

        fieldLevelList: ['yes', 'no'],

        setEmpty: function () {
            this.data = {
                table: {},
                fieldTable:  {},
                fieldTableQuickAccess: {}
            };
            this.implementationHash = {};
            this.forbiddenFieldsCache = {};
            this.implementationClassMap = {};
            this.forbiddenAttributesCache = {};
        },

        getImplementation: function (scope) {
            if (!(scope in this.implementationHash)) {
                var implementationClass = Acl;
                if (scope in this.implementationClassMap) {
                    implementationClass = this.implementationClassMap[scope];
                }
                var obj = new implementationClass(this.getUser(), scope, this.aclAllowDeleteCreated);
                this.implementationHash[scope] = obj;
            }
            return this.implementationHash[scope];
        },

        getUser: function () {
            return this.user;
        },

        set: function (data) {
            data = data || {};
            this.data = data;
            this.data.table = this.data.table || {};
            this.data.fieldTable = this.data.fieldTable || {};
            this.data.attributeTable = this.data.attributeTable || {};
        },

        get: function (name) {
            return this.data[name] || null;
        },

        clear: function () {
            this.setEmpty();
        },

        checkScopeHasAcl: function (scope) {
            var data = (this.data.table || {})[scope];
            if (typeof data === 'undefined') {
                return false;
            }
            return true;
        },

        checkScope: function (scope, action, precise) {
            var data = (this.data.table || {})[scope];
            if (typeof data === 'undefined') {
                data = null;
            }
            return this.getImplementation(scope).checkScope(data, action, precise);
        },

        checkModel: function (model, action, precise) {
            var scope = model.name;

            // todo move this to custom acl
            if (action == 'edit') {
                if (!model.isEditable()) {
                    return false;
                }
            }
            if (action == 'delete') {
                if (!model.isRemovable()) {
                    return false;
                }
            }

            var data = (this.data.table || {})[scope];
            if (typeof data === 'undefined') {
                data = null;
            }

            var impl = this.getImplementation(scope);

            var methodName = 'checkModel' + Espo.Utils.upperCaseFirst(action);
            if (methodName in impl) {
                return impl[methodName](model, data, precise);
            }

            return impl.checkModel(model, data, action, precise);
        },

        check: function (subject, action, precise) {
            if (typeof subject === 'string') {
                return this.checkScope(subject, action, precise);
            } else {
                return this.checkModel(subject, action, precise);
            }
        },

        checkIsOwner: function (model) {
            return this.getImplementation(model.name).checkIsOwner(model);
        },

        checkInTeam: function (model) {
            return this.getImplementation(model.name).checkInTeam(model);
        },

        checkAssignmentPermission: function (user) {
            return this.checkPermission('assignmentPermission', user);
        },

        checkUserPermission: function (user) {
            return this.checkPermission('userPermission', user);
        },

        checkPermission: function (permission, user) {
            var result = false;

            if (this.getUser().isAdmin()) {
                result = true;
            } else {
                if (this.get(permission) === 'no') {
                    if (user.id == this.getUser().id) {
                        result = true;
                    }
                } else if (this.get(permission) === 'team') {
                    if (user.has('teamsIds')) {
                        user.get('teamsIds').forEach(function (id) {
                            if (~(this.getUser().get('teamsIds') || []).indexOf(id)) {
                                result = true;
                            }
                        }, this);
                    }
                } else {
                    result = true;
                }
            }
            return result;
        },

        getScopeForbiddenFieldList: function (scope, action, thresholdLevel) {
            action = action || 'read';
            thresholdLevel = thresholdLevel || 'no';

            var key = scope + '_' + action + '_' + thresholdLevel;
            if (key in this.forbiddenFieldsCache) {
                return this.forbiddenFieldsCache[key];
            }

            var levelList = this.fieldLevelList.slice(this.fieldLevelList.indexOf(thresholdLevel));

            var fieldTableQuickAccess = this.data.fieldTableQuickAccess || {};
            var scopeData = fieldTableQuickAccess[scope] || {};
            var fieldsData = scopeData.fields || {};
            var actionData = fieldsData[action] || {};

            var fieldList = [];
            levelList.forEach(function (level) {
                var list = actionData[level] || [];
                list.forEach(function (field) {
                    if (~fieldList.indexOf(field)) return;
                    fieldList.push(field);
                }, this);
            }, this);

            this.forbiddenFieldsCache[key] = fieldList;

            return fieldList;
        },

        getScopeForbiddenAttributeList: function (scope, action, thresholdLevel) {
            action = action || 'read';
            thresholdLevel = thresholdLevel || 'no';

            var key = scope + '_' + action + '_' + thresholdLevel;
            if (key in this.forbiddenAttributesCache) {
                return this.forbiddenAttributesCache[key];
            }

            var levelList = this.fieldLevelList.slice(this.fieldLevelList.indexOf(thresholdLevel));

            var fieldTableQuickAccess = this.data.fieldTableQuickAccess || {};
            var scopeData = fieldTableQuickAccess[scope] || {};

            var attributesData = scopeData.attributes || {};
            var actionData = attributesData[action] || {};

            var attributeList = [];
            levelList.forEach(function (level) {
                var list = actionData[level] || [];
                list.forEach(function (attribute) {
                    if (~attributeList.indexOf(attribute)) return;
                    attributeList.push(attribute);
                }, this);
            }, this);

            this.forbiddenAttributesCache[key] = attributeList;

            return attributeList;
        },

        checkTeamAssignmentPermission: function (teamId) {
            if (this.get('assignmentPermission') === 'all') return true;
            return ~this.getUser().getLinkMultipleIdList('teams').indexOf(teamId);
        }

    });

    AclManager.extend = Backbone.Router.extend;

    return AclManager;
});

