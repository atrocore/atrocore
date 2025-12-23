/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationship', ['view', 'views/record/list'], function (Dep, List) {
    return Dep.extend({
        template: 'record/compare/relationship',

        relationshipsFields: [],

        instances: [],

        columns: [],

        relationFields: [],

        selectFields: ['id'],

        deferRendering: false,

        setup() {
            this.scope = this.options.scope;
            this.relationship = this.options.relationship ?? {};
            this.collection = this.options.collection;
            this.models = this.options.models;
            this.merging = this.options.merging;
            if (this.collection) {
                this.models = this.collection.models;
            }
            this.model = this.options.model;
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.columns = this.options.columns
            this.fields = [];
            this.relationFields = [];
            this.relationModels = {};
            this.isLinkedColumns = 'isLinked58894';
            this.linkedEntities = []
            this.tableRows = [];
            this.hasToManyRecords = false;

            this.relationName = this.relationName ?? this.relationship.relationName;

            this.listenTo(this.model, 'select-model', (modelId) => {
                let selectedIndex = this.models.findIndex(model => model.id === modelId) || 0;
                this.tableRows.forEach(row => {

                    row.entityValueKeys.forEach((data, index) => {
                        if (!row.isRelationField) {
                            return;
                        }
                        let view = this.getView(data.key);
                        if (!view) {
                            return;
                        }
                        let mode = view.mode;
                        // we set the checkbox to edit mode or the relation field to edit mode if the checkbox is true
                        if (selectedIndex === index && (row.field === this.isLinkedColumns || this.relationModels[row.linkedEntityId][index].get(this.isLinkedColumns))) {
                            view.setMode('edit');
                        } else {
                            if (view.initialAttributes) {
                                view.model.set(view.initialAttributes);
                            }
                            view.setMode('detail');
                        }

                        if (mode !== view.mode) {
                            view.reRender();
                        }
                    });
                })

            });

            this.fetchModelsAndSetup();
        },

        fetchModelsAndSetup() {
            this.wait(true)

            this.prepareModels(() => this.setupRelationship(() => this.wait(false)));
        },

        data() {
            return {
                name: this.relationship.name,
                scope: this.scope,
                relationScope: this.relationship.scope,
                columns: this.columns,
                tableRows: this.tableRows,
                columnCountCurrent: this.columns,
                columnLength: this.columns.length,
                showBorders: this.linkedEntities.length > 1,
                hasToManyRecords: this.hasToManyRecords,
                hasManyRecordsMessage: this.translate('thereAreTooManyRecord'),
            }
        },

        setupRelationship(callback) {
            this.tableRows = [];
            this.linkedEntities.forEach((linkedEntity) => {
                let selectedModelId = $('input[name="check-all"]:checked').val() || this.models[0].id;
                let selectedIndex = this.models.findIndex(model => model.id === selectedModelId);
                let data = [];

                data.push({
                    field: this.isLinkedColumns,
                    title: this.translate('isLinked'),
                    label: `<span style="font-weight: bold;"> ${this.translate('isLinked')} </span>`,
                    linkedEntityId: linkedEntity.id,
                    isRelationField: true,
                    entityValueKeys: []
                });

                this.layoutData.layout.forEach(item => {
                    if (item.name.includes('__')) {
                        let parts = item.name.split('__');
                        if (parts.length !== 2 || parts[0] !== this.relationship.relationName) {
                            return;
                        }
                        data.push({
                            field: parts[1],
                            realField: item.name,
                            label: this.translate(parts[1], 'fields', this.relationship.relationName),
                            title: this.translate(parts[1], 'fields', this.relationship.relationName),
                            linkedEntityId: linkedEntity.id,
                            isRelationField: true,
                            entityValueKeys: []
                        });


                    } else {
                        data.push({
                            field: item.name,
                            realField: item.name,
                            label: this.translate(item.name, 'fields', this.relationship.scope),
                            title: this.translate(item.name, 'fields', this.relationship.scope),
                            linkedEntityId: linkedEntity.id,
                            isRelationField: false,
                            entityValueKeys: []
                        });
                    }
                });

                this.relationModels[linkedEntity.id].forEach((model, index) => {
                    let linkedColumnViewKey = null;
                    data.forEach((el, key) => {
                        if (!el.field) {
                            el.entityValueKeys.push({key: null});
                            return;
                        }

                        let fieldModel = el.isRelationField ? model.clone() : linkedEntity.clone();

                        let field = el.field;
                        let viewName = fieldModel.getFieldParam(field, 'view') || this.getFieldManager().getViewName(fieldModel.getFieldType(field))
                        let viewKey = linkedEntity.id + (el.realField || el.field) + index + 'Current';
                        let mode = 'detail';
                        el.entityValueKeys.push({key: viewKey});

                        if (el.field === this.isLinkedColumns) {
                            linkedColumnViewKey = viewKey;
                        }

                        if (this.merging && selectedIndex === el.entityValueKeys.length - 1) {
                            if (field === this.isLinkedColumns || fieldModel.get(this.isLinkedColumns)) {
                                mode = 'edit';
                            }
                        }

                        let isRequired = !!fieldModel.getFieldParam(field, 'required');
                        if (isRequired && el.label[el.label.length - 1] !== '*') {
                            el.label += '*';
                        }

                        this.createView(viewKey, viewName, {
                            el: this.options.el + ` [data-field="${viewKey}"]`,
                            defs: {
                                ...el,
                                name: field,
                                linkedColumnViewKey,
                            },
                            model: fieldModel.clone(),
                            params: {
                                required: isRequired,
                                readOnly: mode === 'detail' || fieldModel.getFieldParam(field, 'readOnly') || !el.isRelationField,
                            },
                            mode: mode,
                            inlineEditDisabled: true,
                            disabled: !el.isRelationField,
                            entityIndex: el.entityValueKeys.length - 1
                        }, view => {
                            view.initialAttributes = view.model.getClonedAttributes();

                            if (field === this.isLinkedColumns) {
                                this.listenTo(view.model, 'change:' + this.isLinkedColumns, () => {
                                    data.forEach(el => {
                                        if (!el.field || el.field === this.isLinkedColumns) {
                                            return;
                                        }

                                        let key = el.entityValueKeys[view.options.entityIndex].key;
                                        const fieldView = this.getView(key);
                                        if (!fieldView) {
                                            return;
                                        }
                                        const mode = fieldView.mode
                                        if (view.model.get(this.isLinkedColumns)) {
                                            fieldView.setMode('edit');
                                        } else {
                                            fieldView.setMode('detail');
                                        }
                                        if (mode !== fieldView.mode) {
                                            fieldView.model = this.relationModels[el.linkedEntityId][view.options.entityIndex].clone();
                                            fieldView.reRender();
                                        }
                                    });
                                });
                            }
                        });
                    });
                });


                if (this.layoutData.layout.length > 1) {
                    data[0].class = 'strong-border';
                }
                this.tableRows.push(...data);
            });
            callback();
        },

        prepareModels(callback) {
            this.getSelectAttributeList(selectFields => {
                let attributesIds = [];
                (this.listLayout || []).forEach(item => {
                    if (item.attributeId && !attributesIds.includes(item.attributeId)) {
                        attributesIds.push(item.attributeId);
                    }
                });

                this.getModelFactory().create(this.relationship.scope, (foreignModel) => {
                    this.getModelFactory().create(this.relationName, (relationModel) => {
                        relationModel.defs.fields[this.isLinkedColumns] = {
                            type: 'bool'
                        }
                        let modelRelationColumnId = this.getModelRelationColumnId();
                        let relationshipRelationColumnId = this.getRelationshipRelationColumnId();

                        let data = {
                            select: selectFields.join(','),
                            attributes: attributesIds.join(','),
                            where: [
                                {
                                    type: 'linkedWith',
                                    attribute: this.relationship.foreign,
                                    value: this.models.map(m => m.id)
                                }
                            ]
                        };

                        data.totalOnly = true;
                        this.ajaxGetRequest(this.relationship.scope, data).success((res) => {

                            data.maxSize = 500 * this.models.length;

                            if (res.total > data.maxSize) {
                                this.hasToManyRecords = true;
                                callback();
                                return;
                            }

                            data.totalOnly = false;
                            data.collectionOnly = true;
                            Promise.all([
                                this.ajaxGetRequest(this.relationship.scope, data),

                                this.ajaxGetRequest(this.relationName, {
                                    maxSize: 500 * this.models.length,
                                    where: [
                                        {
                                            type: 'in',
                                            attribute: modelRelationColumnId,
                                            value: this.models.map(m => m.id)
                                        }
                                    ]
                                })]
                            ).then(results => {
                                let relationList = results[1].list;
                                let uniqueList = {};
                                results[0].list.forEach(v => uniqueList[v.id] = v);
                                this.linkedEntities = Object.values(uniqueList).map(attr => {
                                    let m = foreignModel.clone();
                                    m.set(attr);
                                    return m;
                                });
                                this.linkedEntities.forEach(item => {
                                    this.relationModels[item.id] = [];
                                    this.models.forEach((model, key) => {
                                        let m = relationModel.clone()
                                        m.set(this.isLinkedColumns, false);
                                        relationList.forEach(relationItem => {
                                            if (item.id === relationItem[relationshipRelationColumnId] && model.id === relationItem[modelRelationColumnId]) {
                                                m.set(relationItem);
                                                m.set(this.isLinkedColumns, true);
                                            }
                                        });

                                        this.relationModels[item.id].push(m);
                                    })
                                });

                                callback();
                            });
                        });
                    });
                })
            })
        },

        getSelectAttributeList(callback) {
            this._helper.layoutManager.get(this.relationship.scope, 'list', this.scope + '.' + this.relationship.name, null, function (data) {
                this.layoutData = data
                this.listLayout = this.filterListLayout(data.layout);
                callback(List.prototype.fetchAttributeListFromLayout.call(this));
            }.bind(this));
        },

        modifyAttributeList(attributeList) {
            return List.prototype.modifyAttributeList.call(this, attributeList);
        },

        filterListLayout: function (listLayout) {
            let entityType = this.model.name;

            listLayout = Espo.Utils.cloneDeep(listLayout);

            // remove relation virtual fields
            if (entityType) {
                let toRemove = [];
                listLayout.forEach((item, k) => {
                    let parts = item.name.split('__');
                    if (parts.length === 2) {
                        toRemove.push({number: k, relEntity: parts[0]});
                    }
                });

                toRemove.forEach(item => {
                    if (!this.relationship.relationName || item.relEntity !== this.relationship.relationName) {
                        listLayout.splice(item.number, 1);
                    }
                });
            }

            let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.relationship.scope, 'read');
            if (!forbiddenFieldList.length) {
                return listLayout;
            }

            let checkedViaAclListLayout = [];
            listLayout.forEach(item => {
                if (item.name && forbiddenFieldList.indexOf(item.name) < 0) {
                    checkedViaAclListLayout.push(item);
                }
            });

            return checkedViaAclListLayout;
        },

        getConditionGroupFields(item) {
            return List.prototype.getConditionGroupFields.call(this, item);
        },

        putAttributesToSelect() {
            let attributesIds = [];
            (this.listLayout || []).forEach(item => {
                if (item.attributeId && !attributesIds.includes(item.attributeId)) {
                    attributesIds.push(item.attributeId);
                }
            })

            if (attributesIds.length > 0) {
                this.collection.data.attributes = attributesIds.join(',');
            }
        },

        isFieldEnabled(model, name) {
            if (model.getFieldParam(name, 'notStorable') && model.getFieldParam(name, 'readOnly') && !model.getFieldParam(name, 'virtualField')) {
                return false;
            }

            const disabledParameters = ['disabled', 'layoutDetailDisabled'];

            for (let param of disabledParameters) {
                if (model.getFieldParam(name, param)) {
                    return false
                }
            }

            return true;
        },

        updateBaseUrl() {
        },

        getModelRelationColumnId() {
            let midKeys = this.model.defs.links[this.relationship.name].midKeys;

            if (this.model.defs.links[this.relationship.name].isAssociateRelation) {
                return midKeys[0]
            }

            if (midKeys && midKeys.length === 2) {
                return midKeys[1];
            }

            return this.scope.charAt(0).toLowerCase() + this.scope.slice(1) + 'Id';
        },

        getRelationshipRelationColumnId() {
            let midKeys = this.model.defs.links[this.relationship.name].midKeys;

            if (this.model.defs.links[this.relationship.name].isAssociateRelation) {
                return midKeys[1]
            }

            if (midKeys && midKeys.length === 2) {
                return midKeys[0];
            }

            return this.relationship.scope.charAt(0).toLowerCase() + this.relationship.scope.slice(1) + 'Id';
        },

        getLinkName() {
            return this.relationship.name;
        },

        getSelectedModelIdForData(selectedModelId) {
            return selectedModelId;
        },

        fetch() {
            let selectedModelId = $('input[name="check-all"]:checked').val();
            let selectedIndex = this.models.findIndex(model => model.id === selectedModelId);
            let toUpsert = [];
            let toDelete = [];
            let scope = this.relationName;
            for (let linkedEntity of this.linkedEntities) {
                let isLinkedFieldRow = this.tableRows.find(row => row.field === this.isLinkedColumns && row.linkedEntityId === linkedEntity.id);
                let view = this.getView(isLinkedFieldRow.entityValueKeys[selectedIndex].key);

                if (!view) {
                    continue;
                }

                if (!view.model.get(this.isLinkedColumns)) {
                    if (view.model.get('id')) {
                        toDelete.push(view.model.get('id'));
                    }
                    continue;
                }

                let attr = {};
                attr[this.getModelRelationColumnId()] = this.getSelectedModelIdForData(selectedModelId);
                attr[this.getRelationshipRelationColumnId()] = linkedEntity.id;


                let otherRows = this.tableRows.filter(row => {
                    return row.field && row.linkedEntityId === linkedEntity.id && row.field !== this.isLinkedColumns;
                });

                otherRows.forEach(row => {
                    let view = this.getView(row.entityValueKeys[selectedIndex].key);
                    if (!view) {
                        return;
                    }
                    attr = _.extend({}, attr, view.fetch());
                    if (view.model.get('id')) {
                        attr['id'] = view.model.get('id');
                    }
                });

                toUpsert.push(attr);
            }

            return {
                scope, toUpsert, toDelete
            };
        },

        validate() {
            let selectedModelId = $('input[name="check-all"]:checked').val();
            let selectedIndex = this.models.findIndex(model => model.id === selectedModelId);
            let validate = false;
            for (let linkedEntity of this.linkedEntities) {
                let isLinkedFieldRow = this.tableRows.find(row => row.field === this.isLinkedColumns && row.linkedEntityId === linkedEntity.id);
                let view = this.getView(isLinkedFieldRow.entityValueKeys[selectedIndex].key);

                if (!view || !view.model.get(this.isLinkedColumns)) {
                    continue;
                }

                let otherRows = this.tableRows.filter(row => {
                    return row.field && row.linkedEntityId === linkedEntity.id && row.field !== this.isLinkedColumns;
                });

                otherRows.forEach(row => {
                    let view = this.getView(row.entityValueKeys[selectedIndex].key);
                    if (!view) {
                        return;
                    }
                    validate = validate || view.validate();
                });
            }

            return validate;
        },

        changeViewMode(newMode) {
            let selectedModelId = $('input[name="check-all"]:checked').val();
            let selectedIndex = this.models.findIndex(model => model.id === selectedModelId);
            if (selectedIndex === -1) {
                selectedIndex = 0;
            }

            this.tableRows.forEach(row => {
                row.entityValueKeys.forEach((data, index) => {
                    if (!row.isRelationField) {
                        return;
                    }

                    let view = this.getView(data.key);

                    if (!view) {
                        return;
                    }

                    if (view.model.getFieldParam('readOnly')) {
                        return;
                    }

                    let mode = view.mode;

                    if (selectedIndex === index && (row.field === this.isLinkedColumns || this.relationModels[row.linkedEntityId][index].get(this.isLinkedColumns))) {
                        if (view.initialAttributes) {
                            view.model.set(view.initialAttributes);
                        }
                        view.setMode(newMode);
                        if (mode !== view.mode) {
                            view.reRender();
                        }
                    }
                });
            });
        }
    });
})