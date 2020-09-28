

Espo.define('views/admin/layouts/detail', 'views/admin/layouts/grid', function (Dep) {

    return Dep.extend({

        dataAttributeList: ['name', 'fullWidth', 'customLabel', 'noLabel'],

        panelDataAttributeList: ['panelName', 'style', 'dynamicLogicVisible'],

        dataAttributesDefs: {
            fullWidth: {
                type: 'bool'
            },
            name: {
                readOnly: true
            },
            customLabel: {
                type: 'varchar',
                readOnly: true
            },
            noLabel: {
                type: 'bool',
                readOnly: true
            }
        },

        panelDataAttributesDefs: {
            panelName: {
                type: 'varchar',
            },
            style: {
                type: 'enum',
                options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
                translation: 'LayoutManager.options.style'
            },
            dynamicLogicVisible: {
                type: 'base',
                view: 'views/admin/field-manager/fields/dynamic-logic-conditions'
            }
        },

        defaultPanelFieldList: ['modifiedAt', 'createdAt', 'modifiedBy', 'createdBy', 'assignedUser', 'teams'],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.panelDataAttributesDefs = Espo.Utils.cloneDeep(this.panelDataAttributesDefs);
            this.panelDataAttributesDefs.dynamicLogicVisible.scope = this.scope;

            this.wait(true);
            this.loadLayout(function () {

                this.setupPanels();
                this.wait(false);
            }.bind(this));
        },

        loadLayout: function (callback) {
            var layout;
            var model;

            var promiseList = [];

            promiseList.push(
                new Promise(function (resolve) {
                    this.getModelFactory().create(this.scope, function (m) {
                        this.getHelper().layoutManager.get(this.scope, this.type, function (layoutLoaded) {
                            layout = layoutLoaded;
                            model = m;
                            resolve();
                        });
                    }.bind(this));
                }.bind(this))
            );

            if (~['detail', 'detailSmall'].indexOf(this.type)) {
                promiseList.push(
                    new Promise(function (resolve) {
                        this.getHelper().layoutManager.get(this.scope, 'sidePanels' + Espo.Utils.upperCaseFirst(this.type), function (layoutLoaded) {
                            this.sidePanelsLayout = layoutLoaded;
                            resolve();
                        }.bind(this));
                    }.bind(this))
                );
            }

            Promise.all(promiseList).then(function () {
                this.readDataFromLayout(model, layout);
                if (callback) {
                    callback();
                }
            }.bind(this));

        },

        readDataFromLayout: function (model, layout) {
            var allFields = [];
            for (var field in model.defs.fields) {
                if (this.isFieldEnabled(model, field)) {
                    allFields.push(field);
                }
            }

            this.enabledFields = [];
            this.disabledFields = [];

            this.panels = layout;

            layout.forEach(function (panel) {
                panel.rows.forEach(function (row) {
                    row.forEach(function (cell, i) {
                        if (i == this.columnCount) {
                            return;
                        }
                        this.enabledFields.push(cell.name);
                    }.bind(this));
                }.bind(this));
            }.bind(this));

            allFields.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));


            for (var i in allFields) {
                if (!_.contains(this.enabledFields, allFields[i])) {
                    this.disabledFields.push(allFields[i]);
                }
            }
        },

        isFieldEnabled: function (model, name) {
            if (this.hasDefaultPanel()) {
                if (this.defaultPanelFieldList.indexOf(name) !== -1) {
                    return false;
                }
            }
            return !model.getFieldParam(name, 'disabled') && !model.getFieldParam(name, 'layoutDetailDisabled');
        },

        hasDefaultPanel: function () {
            if (this.getMetadata().get(['clientDefs', this.scope, 'defaultSidePanel', this.viewType]) === false) return false;
            if (this.getMetadata().get(['clientDefs', this.scope, 'defaultSidePanelDisabled'])) return false;

            if (this.sidePanelsLayout) {
                for (var name in this.sidePanelsLayout) {
                    if (name === 'default' && this.sidePanelsLayout[name].disabled) {
                        return false;
                    }
                }
            }

            return true;
        }
    });
});
