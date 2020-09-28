

Espo.define('views/admin/layouts/filters', 'views/admin/layouts/rows', function (Dep) {

    return Dep.extend({

        dataAttributeList: ['name'],

        editable: false,

        ignoreList: [],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.wait(true);
            this.loadLayout(function () {
                this.wait(false);
            }.bind(this));
        },

        loadLayout: function (callback) {
            this.getModelFactory().create(this.scope, function (model) {
                this.getHelper().layoutManager.get(this.scope, this.type, function (layout) {

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
                    for (var i in layout) {
                        this.enabledFields.push({
                            name: layout[i],
                            label: this.getLanguage().translate(layout[i], 'fields', this.scope)
                        });
                        this.enabledFieldsList.push(layout[i]);
                    }

                    for (var i in allFields) {
                        if (!_.contains(this.enabledFieldsList, allFields[i])) {
                            this.disabledFields.push({
                                name: allFields[i],
                                label: this.getLanguage().translate(allFields[i], 'fields', this.scope)
                            });
                        }
                    }
                    this.rowLayout = this.enabledFields;

                    for (var i in this.rowLayout) {
                        this.rowLayout[i].label = this.getLanguage().translate(this.rowLayout[i].name, 'fields', this.scope);
                    }

                    callback();
                }.bind(this), false);
            }.bind(this));
        },

        fetch: function () {
            var layout = [];
            $("#layout ul.enabled > li").each(function (i, el) {
                layout.push($(el).data('name'));
            }.bind(this));
            return layout;
        },

        checkFieldType: function (type) {
            return this.getFieldManager().checkFilter(type);
        },

        validate: function () {
            return true;
        },

        isFieldEnabled: function (model, name) {
            if (this.ignoreList.indexOf(name) != -1) {
                return false;
            }
            return !model.getFieldParam(name, 'disabled') && !model.getFieldParam(name, 'layoutFiltersDisabled');
        }

    });
});

