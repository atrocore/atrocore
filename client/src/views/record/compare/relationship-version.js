/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationship-version', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({

        getSelectedModelIdForData(selectedModelId) {
            return this.model.id;
        },

        prepareModels(callback) {
            const versionModel = this.options.versionModel;
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
                            type: 'or',
                            value: [
                                {
                                    type: 'linkedWith',
                                    attribute: this.relationship.foreign,
                                    value: [this.model.id]
                                },
                                {
                                    type: 'in',
                                    attribute: 'id',
                                    value: versionModel.get('__relationships')?.[this.relationship.name]?.['ids'] ?? []
                                }
                            ]
                        }
                    ]
                };

                data.totalOnly = true;
                this.ajaxGetRequest(this.relationship.scope, data).success((res) => {

                    data.maxSize = 500;

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
                            maxSize: 500,
                            where: [
                                {
                                    type: 'in',
                                    attribute: modelRelationColumnId,
                                    value: [this.model.id]
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
                            [this.model, versionModel].forEach((model, key) => {
                                let m = relationModel.clone()
                                m.set(this.isLinkedColumns, false);
                                (model === versionModel ? (versionModel.get('__relationships')?.[this.relationship.name]?.relationships ?? []) : relationList)
                                    .forEach(relationItem => {
                                        if (item.id === relationItem[relationshipRelationColumnId] && this.model.id === relationItem[modelRelationColumnId]) {
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
    })
})