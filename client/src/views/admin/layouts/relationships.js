

Espo.define('views/admin/layouts/relationships', 'views/admin/layouts/rows', function (Dep) {

    return Dep.extend({

        dataAttributeList: ['name', 'style'],

        editable: true,

        dataAttributesDefs: {
            style: {
                type: 'enum',
                options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
                translation: 'LayoutManager.options.style'
            },
            name: {
                readOnly: true
            }
        },

        languageCategory: 'links',

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
                    for (var field in model.defs.links) {
                        if (['hasMany', 'hasChildren'].indexOf(model.defs.links[field].type) != -1) {
                            if (this.isLinkEnabled(model, field)) {
                                allFields.push(field);
                            }
                        }
                    }
                    allFields.sort(function (v1, v2) {
                        return this.translate(v1, 'links', this.scope).localeCompare(this.translate(v2, 'links', this.scope));
                    }.bind(this));

                    this.enabledFieldsList = [];

                    this.enabledFields = [];
                    this.disabledFields = [];
                    for (var i in layout) {
                        var item = layout[i];
                        var o;
                        if (typeof item == 'string' || item instanceof String) {
                            o = {
                                name: item,
                                label: this.getLanguage().translate(item, 'links', this.scope)
                            };
                        } else {
                            o = item;
                            o.label =  this.getLanguage().translate(o.name, 'links', this.scope);
                        }
                        this.dataAttributeList.forEach(function (attribute) {
                            if (attribute === 'name') return;
                            if (attribute in o) return;

                            var value = this.getMetadata().get(['clientDefs', this.scope, 'relationshipPanels', o.name, attribute]);
                            if (value === null) return;
                            o[attribute] = value;
                        }, this);

                        this.enabledFields.push(o);
                        this.enabledFieldsList.push(o.name);
                    }

                    for (var i in allFields) {
                        if (!_.contains(this.enabledFieldsList, allFields[i])) {
                            this.disabledFields.push({
                                name: allFields[i],
                                label: this.getLanguage().translate(allFields[i], 'links', this.scope)
                            });
                        }
                    }
                    this.rowLayout = this.enabledFields;

                    for (var i in this.rowLayout) {
                        this.rowLayout[i].label = this.getLanguage().translate(this.rowLayout[i].name, 'links', this.scope);
                    }

                    callback();
                }.bind(this), false);
            }.bind(this));
        },

        validate: function () {
            return true;
        },

        isLinkEnabled: function (model, name) {
            return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
        }
    });
});

