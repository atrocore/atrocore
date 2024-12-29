/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/compare/relationship', 'view', function (Dep) {
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
            this.baseModel = this.options.model;
            this.relationship = this.options.relationship ?? {};
            this.collection = this.options.collection;
            this.models = this.options.models;
            this.merging = this.options.merging;
            if (this.collection) {
                this.models = this.collection.models;
            }
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.columns = this.options.columns
            this.fields = [];
            this.relationFields = [];
            this.relationModels = {};
            this.isLinkedColumns = 'isLinked58894';
            this.selectFields = this.selectFields ?? ['id'];
            this.linkedEntities = []
            this.tableRows = [];
            this.hasToManyRecords = false;

            this.relationName = this.relationName ?? this.relationship.relationName;

            let fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', 'name', 'type']);
            if (fieldType) {
                this.selectFields.push('name')
            }

            this.listenTo(this.model, 'select-model', (modelId) => {

                let selectedIndex = this.models.findIndex(model => model.id === modelId);
                this.tableRows.forEach(row => {

                    row.entityValueKeys.forEach((data, index) => {
                        let view = this.getView(data.key);
                        if (!view) {
                            return;
                        }
                        let mode = view.mode;
                        // we set the checkbox to edit mode or the relation field to edit mode if the checkbox is true
                        if (selectedIndex === index && (row.field === this.isLinkedColumns || this.relationModels[row.linkedEntityId][index].get(this.isLinkedColumns))) {
                            view.setMode('edit');
                        } else {
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
                let selectedModelId = $('input[name="check-all"]:checked').val();
                let selectedIndex = this.models.findIndex(model => model.id === selectedModelId);
                let data = this.getFieldColumns(linkedEntity);

                this.getRelationAdditionalFields().forEach(field => {

                    data.push({
                        field: field,
                        label: this.translate(field, 'fields', this.relationName) ,
                        title: this.translate(field, 'fields', this.relationName),
                        linkedEntityId: linkedEntity.id,
                        entityValueKeys: []
                    });
                })

                this.relationModels[linkedEntity.id].forEach((model, index) => {

                    data.forEach((el,key) => {
                        if (!el.field) {
                            el.entityValueKeys.push({key: null});
                            return;
                        }

                        let field = el.field;
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field))
                        let viewKey = linkedEntity.id + field + index + 'Current';
                        let mode = 'detail';
                        el.entityValueKeys.push({key: viewKey});

                        if (this.merging && selectedIndex === el.entityValueKeys.length - 1) {
                            if (field === this.isLinkedColumns || model.get(this.isLinkedColumns)) {
                                mode = 'edit';
                            }
                        }

                        let isRequired = !!model.getFieldParam(field,'required');
                        if(isRequired && el.label[el.label.length-1] !== '*') {
                            el.label += '*';
                        }
                        this.createView(viewKey, viewName, {
                            el: this.options.el + ` [data-field="${viewKey}"]`,
                            model: model.clone(),
                            defs: {
                                name: field,
                            },
                            params: {
                                required: isRequired
                            },
                            mode: mode,
                            inlineEditDisabled: true,
                            entityIndex: el.entityValueKeys.length - 1
                        }, view => {
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

                if (this.getRelationAdditionalFields().length && data[0].entityValueKeys.length && this.linkedEntities.length > 1) {
                    data[0].class = 'strong-border';
                }
                this.tableRows.push(...data);
            });
            callback();
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, (relationModel) => {
                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }
                let modelRelationColumnId = this.getModelRelationColumnId();
                let relationshipRelationColumnId = this.getRelationshipRelationColumnId();
                let data = {
                    select: this.selectFields.join(','),
                    where: [
                        {
                            type: 'linkedWith',
                            attribute: this.relationship.foreign,
                            value: this.collection.models.map(m => m.id)
                        }
                    ]
                };

                data.totalOnly = true;
                this.ajaxGetRequest(this.relationship.scope, data).success((res) => {

                    data.maxSize = 500 * this.collection.models.length;

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
                            maxSize: 500 * this.collection.models.length,
                            where: [
                                {
                                    type: 'in',
                                    attribute: modelRelationColumnId,
                                    value: this.collection.models.map(m => m.id)
                                }
                            ]
                        })]
                    ).then(results => {
                        let relationList = results[1].list;
                        let uniqueList = {};
                        results[0].list.forEach(v => uniqueList[v.id] = v);
                        this.linkedEntities = Object.values(uniqueList)
                        this.linkedEntities.forEach(item => {
                            this.relationModels[item.id] = [];
                            this.collection.models.forEach((model, key) => {
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
        },

        getRelationAdditionalFields() {
            if (this.relationFields.length) {
                return this.relationFields;
            }

            this.getModelFactory().create(this.relationName, relationModel => {
                for (let field in relationModel.defs.fields) {
                    if (!this.isFieldEnabled(relationModel, field) || relationModel.getFieldParam(field, 'relationField')) {
                        continue;
                    }

                    if (['createdAt', 'modifiedAt', 'createdBy', 'modifiedBy', 'id'].includes(field)) {
                        continue;
                    }

                    this.relationFields.push(field);
                }
            });

            this.relationFields.sort((v1, v2) => {
                return this.translate(v1, 'fields', this.relationName).localeCompare(this.translate(v2, 'fields', this.relationName));
            })

            return this.relationFields;
        },

        getFieldColumns(linkedEntity) {
            let data = [];
            if (this.relationship.scope === 'File') {
                data.push({
                    field: this.isLinkedColumns,
                    label: linkedEntity.name,
                    title: linkedEntity.name,
                    isField: true,
                    key: linkedEntity.id,
                    linkedEntityId: linkedEntity.id,
                    entityValueKeys: []
                });
                this.getModelFactory().create('File', fileModel => {
                    fileModel.set(linkedEntity);
                    let viewName = fileModel.getFieldParam('preview', 'view') || this.getFieldManager().getViewName(fileModel.getFieldType('preview'));
                    let viewKey = linkedEntity.id;
                    this.createView(viewKey, viewName, {
                        el: `${this.options.el} [data-key="${viewKey}"] .attachment-preview`,
                        model: fileModel,
                        readOnly: true,
                        defs: {
                            name: 'preview',
                        },
                        mode: 'list',
                        inlineEditDisabled: true,
                    }, view => {
                        view.previewSize = 'small';
                        view.once('after:render', () => {
                            this.$el.find(`[data-key="${viewKey}"]`).append(`<div class="file-link">
<a href="?entryPoint=download&id=${linkedEntity.id}" download="" title="Download">
 <span class="glyphicon glyphicon-download-alt small"></span>
 </a> 
 <a href="/#File/view/${linkedEntity.id}" title="${linkedEntity.name}" class="link" data-id="${linkedEntity.id}">${linkedEntity.name}</a>
 </div>`);
                        })
                    });
                });
            } else {
                data.push({
                    field: this.isLinkedColumns,
                    title: linkedEntity.name,
                    label: `<a href="#/${this.relationship.scope}/view/${linkedEntity.id}"> ${linkedEntity.name ?? linkedEntity.id} </a>`,
                    linkedEntityId: linkedEntity.id,
                    entityValueKeys: []
                });
            }

            return data;
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
            return (midKeys && midKeys.length === 2) ? midKeys[1] : this.scope.toLowerCase() + 'Id';
        },

        getRelationshipRelationColumnId() {
            let midKeys = this.model.defs.links[this.relationship.name].midKeys;
            return (midKeys && midKeys.length === 2) ? midKeys[0] : this.relationship.scope.toLowerCase() + 'Id';
        },

        getLinkName() {
            return this.relationship.name;
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
                    if(view.model.get('id')) {
                        toDelete.push(view.model.get('id'));
                    }
                    continue;
                }

                let attr = {};
                attr[this.getModelRelationColumnId()] = selectedModelId;
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
        }
    })
})