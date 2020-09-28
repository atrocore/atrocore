

Espo.define('views/admin/layouts/list', 'views/admin/layouts/rows', function (Dep) {

    return Dep.extend({

        dataAttributeList: ['name', 'width', 'link', 'notSortable', 'align', 'view', 'customLabel', 'widthPx'],

        dataAttributesDefs: {
            link: {type: 'bool'},
            width: {type: 'float'},
            notSortable: {type: 'bool'},
            align: {
                type: 'enum',
                options: ["left", "right"]
            },
            view: {
                type: 'varchar',
                readOnly: true
            },
            customLabel: {
                type: 'varchar',
                readOnly: true
            },
            widthPx: {
                type: 'int',
                readOnly: true
            },
            name: {
                type: 'varchar',
                readOnly: true
            }
        },

        editable: true,

        languageCategory: 'fields',

        ignoreList: [],

        ignoreTypeList: [],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.wait(true);
            this.loadLayout(function () {
                this.wait(false);
            }.bind(this));
        },

        loadLayout: function (callback) {
            this.getModelFactory().create(Espo.Utils.hyphenToUpperCamelCase(this.scope), function (model) {
                this.getHelper().layoutManager.get(this.scope, this.type, function (layout) {
                    this.readDataFromLayout(model, layout);
                    if (callback) {
                        callback();
                    }
                }.bind(this), false);
            }.bind(this));
        },

        readDataFromLayout: function (model, layout) {
            var allFields = [];
            for (var field in model.defs.fields) {
                if (this.checkFieldType(model.getFieldParam(field, 'type')) && this.isFieldEnabled(model, field)) {

                    allFields.push(field);
                }
            }

            allFields.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));

            this.enabledFieldsList = [];

            this.enabledFields = [];
            this.disabledFields = [];

            var labelList = [];
            var duplicateLabelList = [];

            for (var i in layout) {
                var label = this.getLanguage().translate(layout[i].name, 'fields', this.scope);
                if (~labelList.indexOf(label)) {
                    duplicateLabelList.push(label);
                }
                labelList.push(label);
                this.enabledFields.push({
                    name: layout[i].name,
                    label: label
                });
                this.enabledFieldsList.push(layout[i].name);
            }

            for (var i in allFields) {
                if (!_.contains(this.enabledFieldsList, allFields[i])) {
                    var label = this.getLanguage().translate(allFields[i], 'fields', this.scope);
                    if (~labelList.indexOf(label)) {
                        duplicateLabelList.push(label);
                    }
                    labelList.push(label);
                    var fieldName = allFields[i];
                    var o = {
                        name: fieldName,
                        label: label
                    };
                    var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', fieldName, 'type']);
                    if (fieldType) {
                        if (this.getMetadata().get(['fields', fieldType, 'notSortable'])) {
                            o.notSortable = true;
                        }
                    }
                    this.disabledFields.push(o);
                }
            }

            this.enabledFields.forEach(function (item) {
                if (~duplicateLabelList.indexOf(item.label)) {
                    item.label += ' (' + item.name + ')';
                }
            }, this);
            this.disabledFields.forEach(function (item) {
                if (~duplicateLabelList.indexOf(item.label)) {
                    item.label += ' (' + item.name + ')';
                }
            }, this);

            this.rowLayout = layout;

            for (var i in this.rowLayout) {
                var label = this.getLanguage().translate(this.rowLayout[i].name, 'fields', this.scope);
                this.enabledFields.forEach(function (item) {
                    if (item.name === this.rowLayout[i].name) {
                        label = item.label;
                    }
                }, this);
                this.rowLayout[i].label = label;
            }
        },

        checkFieldType: function (type) {

            return true;
        },

        isFieldEnabled: function (model, name) {
            if (this.ignoreList.indexOf(name) != -1) {
                return false;
            }
            if (this.ignoreTypeList.indexOf(model.getFieldParam(name, 'type')) != -1) {
                return false;
            }
            return !model.getFieldParam(name, 'disabled') && !model.getFieldParam(name, 'layoutListDisabled');
        }

    });
});


