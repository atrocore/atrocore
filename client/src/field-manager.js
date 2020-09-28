

 Espo.define('field-manager', [], function () {

    var FieldManager = function (defs, metadata) {
        this.defs = defs || {};
        this.metadata = metadata;
    };

    _.extend(FieldManager.prototype, {

        defs: null,

        metadata: null,

        getParamList: function (fieldType) {
            if (fieldType in this.defs) {
                return this.defs[fieldType].params || [];
            }
            return [];
        },

        checkFilter: function (fieldType) {
            if (fieldType in this.defs) {
                if ('filter' in this.defs[fieldType]) {
                    return this.defs[fieldType].filter;
                } else {
                    return false;
                }
            }
            return false;
        },

        isMergeable: function (fieldType) {
            if (fieldType in this.defs) {
                return !this.defs[fieldType].notMergeable;
            }
            return false;
        },

        getEntityAttributeList: function (entityType) {
            return this.getScopeAttributeList(entityType);
        },

        getScopeAttributeList: function (entityType) {
            var list = [];
            var defs = this.metadata.get('entityDefs.' + entityType + '.fields') || {};
            Object.keys(defs).forEach(function (field) {
                this.getAttributeList(defs[field]['type'], field).forEach(function (attr) {
                    if (!~list.indexOf(attr)) {
                        list.push(attr);
                    }
                });
            }, this);
            return list;
        },

        getActualAttributeList: function (fieldType, fieldName) {
            var fieldNames = [];
            if (fieldType in this.defs) {
                if ('actualFields' in this.defs[fieldType]) {
                    var actualfFields = this.defs[fieldType].actualFields;

                    var naming = 'suffix';
                    if ('naming' in this.defs[fieldType]) {
                        naming = this.defs[fieldType].naming;
                    }
                    if (naming == 'prefix') {
                        actualfFields.forEach(function (f) {
                            fieldNames.push(f + Espo.Utils.upperCaseFirst(fieldName));
                        });
                    } else {
                        actualfFields.forEach(function (f) {
                            fieldNames.push(fieldName + Espo.Utils.upperCaseFirst(f));
                        });
                    }
                } else {
                    fieldNames.push(fieldName);
                }
            }
            return fieldNames;
        },

        getNotActualAttributeList: function (fieldType, fieldName) {
            var fieldNames = [];
            if (fieldType in this.defs) {
                if ('notActualFields' in this.defs[fieldType]) {
                    var notActualFields = this.defs[fieldType].notActualFields;

                    var naming = 'suffix';
                    if ('naming' in this.defs[fieldType]) {
                        naming = this.defs[fieldType].naming;
                    }
                    if (naming == 'prefix') {
                        notActualFields.forEach(function (f) {
                            if (f === '') {
                                fieldNames.push(fieldName);
                            } else {
                                fieldNames.push(f + Espo.Utils.upperCaseFirst(fieldName));
                            }
                        });
                    } else {
                        notActualFields.forEach(function (f) {
                            fieldNames.push(fieldName + Espo.Utils.upperCaseFirst(f));
                        });
                    }
                }
            }
            return fieldNames;
        },

        getAttributeList: function (fieldType, fieldName) {
            return _.union(this.getActualAttributeList(fieldType, fieldName), this.getNotActualAttributeList(fieldType, fieldName));
        },

        getScopeFieldList: function (scope) {
            return Object.keys(this.metadata.get('entityDefs.' + scope + '.fields') || {});
        },

        getViewName: function (fieldType) {
            if (fieldType in this.defs) {
                if ('view' in this.defs[fieldType]) {
                    return this.defs[fieldType].view;
                }
            }
            return 'views/fields/' + Espo.Utils.camelCaseToHyphen(fieldType);
        },

        getParams: function (fieldType) {
            return this.getParamList(fieldType);
        },

        getEntityAttributes: function (entityType) {
            return this.getEntityAttributeList(entityType);
        },

        getAttributes: function (fieldType, fieldName) {
            return this.getAttributeList(fieldType, fieldName);
        },

        getActualAttributes: function (fieldType, fieldName) {
            return this.getActualAttributeList(fieldType, fieldName);
        },

        getNotActualAttributes: function (fieldType, fieldName) {
            return this.getNotActualAttributeList(fieldType, fieldName);
        }

    });

    return FieldManager;

});


