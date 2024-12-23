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

        setup() {
            this.scope = this.options.scope;
            this.baseModel = this.options.model;
            this.relationship = this.options.relationship ?? {};
            this.collection = this.options.collection;
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.columns = this.options.columns
            this.checkedList = [];
            this.enabledFixedHeader = false;
            this.dragableListRows = false;
            this.showMore = false
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

            this.fetchModelsAndSetup();
        },

        fetchModelsAndSetup() {
            this.wait(true)

            this.prepareModels( () => this.setupRelationship(() => this.wait(false)));
        },

        data() {
            let minWidth = 150;

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
                minWidth
            }
        },

        setupRelationship(callback) {
            this.tableRows = [];
            this.linkedEntities.forEach((linkEntity) => {
                let data = this.getFieldColumns(linkEntity);

                this.getRelationAdditionalFields().forEach(field => {
                    data.push({
                        field: field,
                        label: this.translate(field, 'fields', this.relationName),
                        title: this.translate(field, 'fields', this.relationName),
                        entityValueKeys: []
                    });
                })

                this.relationModels[linkEntity.id].forEach((model, index) => {
                    data.forEach(el => {
                        if(!el.field) {
                            el.entityValueKeys.push({key: null});
                            return;
                        }
                        let field = el.field;
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field))
                        let viewKey = linkEntity.id + field + index + 'Current';
                        el.entityValueKeys.push({key: viewKey})
                        this.createView(viewKey, viewName, {
                            el: this.options.el + ` [data-field="${viewKey}"]`,
                            model: model,
                            readOnly: true,
                            defs: {
                                name: field,
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
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

        afterRender() {
            Dep.prototype.afterRender.call(this)
            $('.not-approved-field').remove();
            $('.translated-automatically-field').remove();
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, (relationModel) => {
                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }
                let modelRelationColumnId = this.scope.toLowerCase() + 'Id';
                let relationshipRelationColumnId = this.relationship.scope.toLowerCase() + 'Id';
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

                    if(res.total > data.maxSize) {
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
                        list = Object.values(uniqueList)
                        this.linkedEntities = list;
                        list.forEach(item => {
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

            this.getModelFactory().create(this.relationName, relationModel  => {
                for (let field in relationModel.defs.fields) {
                    if(!this.isFieldEnabled(relationModel, field) || relationModel.getFieldParam(field, 'relationField')){
                        continue;
                    }

                    if(['createdAt', 'modifiedAt', 'createdBy', 'modifiedBy', 'id'].includes(field)) {
                        continue;
                    }

                    this.relationFields.push(field);
                }
            });

            this.relationFields.sort((v1, v2) =>{
                return this.translate(v1, 'fields', this.relationName).localeCompare(this.translate(v2, 'fields', this.relationName));
            })

            return this.relationFields;
        },

        getFieldColumns(linkEntity) {
            let data = [];
            if (this.relationship.scope === 'File') {
                data.push({
                    field: this.isLinkedColumns,
                    label: linkEntity.name,
                    title: linkEntity.name,
                    isField: true,
                    key: linkEntity.id,
                    entityValueKeys: []
                });
                this.getModelFactory().create('File', fileModel => {
                    fileModel.set(linkEntity);
                    let viewName = fileModel.getFieldParam('preview', 'view') || this.getFieldManager().getViewName(fileModel.getFieldType('preview'));
                    let viewKey = linkEntity.id;
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
<a href="?entryPoint=download&id=${linkEntity.id}" download="" title="Download">
 <span class="glyphicon glyphicon-download-alt small"></span>
 </a> 
 <a href="/#File/view/${linkEntity.id}" title="${linkEntity.name}" class="link" data-id="${linkEntity.id}">${linkEntity.name}</a>
 </div>`);
                        })
                    });
                });
            } else {
                data.push({
                    field: this.isLinkedColumns,
                    title: linkEntity.name,
                    label: `<a href="#/${this.relationship.scope}/view/${linkEntity.id}"> ${linkEntity.name ?? linkEntity.id} </a>`,
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
        }
    })
})