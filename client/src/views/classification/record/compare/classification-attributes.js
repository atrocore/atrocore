/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/classification/record/compare/classification-attributes', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({
        setup() {
            this.relationName = 'ClassificationAttribute';
            Dep.prototype.setup.call(this);
            this.relationship.scope = 'ClassificationAttribute';
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, (relationModel) => {
                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }

                let data = {
                    maxSize: 500 * this.models.length,
                    where: [
                        {
                            type: 'in',
                            attribute: 'classificationId',
                            value: this.models.map(m => m.id)
                        }
                    ]
                };

                this.ajaxGetRequest('ClassificationAttribute', data).success((res) => {
                    let relationList = res.list;
                    let uniqueList = {};
                    relationList.forEach(attr => {
                        let key = attr.attributeId + attr.channel + attr.language;
                        if (uniqueList[key]) {
                            return;
                        }
                        let name = attr.attributeName;
                        if(attr.channelId) {
                            name += ' / ' + attr.channelName;
                        }

                        if(attr.language && attr.language !== 'main') {
                            name += ' / ' + attr.language;
                        }

                        attr.name = name;
                        uniqueList[key] = attr;
                        for (const key in attr) {
                            if(key.includes('value')) {
                                attr[key] = null;
                            }
                        }
                    });

                    this.linkedEntities = Object.values(uniqueList);
                    this.linkedEntities.forEach(item => {
                        this.relationModels[item.id] = [];
                        this.models.forEach((model, key) => {
                            let m = relationModel.clone()
                            m.set(item);
                            m.set(this.isLinkedColumns, false);

                            relationList.forEach(relationItem => {
                                if (item.attributeId === relationItem['attributeId']
                                    && item.channelId === relationItem['channelId']
                                    && item.language === relationItem['language']
                                    && model.id === relationItem['classificationId']
                                ) {
                                    m.set(relationItem);
                                    m.set(this.isLinkedColumns, true);
                                }
                            });
                            this.relationModels[item.id].push(m);
                        });
                    });
                    callback();
                });
            });
        },


        getModelRelationColumnId() {
            return 'classificationId';
        },

        getRelationshipRelationColumnId() {
            return 'attributeId';
        },

        getRelationAdditionalFields() {
            if (this.relationFields.length) {
                return this.relationFields;
            }

            return this.relationFields = ['value', 'isRequired']
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
                attr['classificationId'] = selectedModelId;
                attr['attributeId'] = linkedEntity.attributeId;
                attr['channelId'] = linkedEntity.channelId ?? '';
                attr['language'] = linkedEntity.language;

                let otherRows = this.tableRows.filter(row => {
                    return row.field && row.linkedEntityId === linkedEntity.id && row.field !== this.isLinkedColumns;
                });

                otherRows.forEach(row => {
                    let view = this.getView(row.entityValueKeys[selectedIndex].key);
                    if (!view) {
                        return;
                    }
                    attr = _.extend({}, attr, view.fetch());
                });

                toUpsert.push(attr);
            }

            return {
                scope, toUpsert, toDelete
            };
        },
    });
});