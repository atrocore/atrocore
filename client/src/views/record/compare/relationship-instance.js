/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationship-instance', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({
        setup() {
            this.instances = this.getMetadata().get(['app', 'comparableInstances']);
            this.instanceComparison = true;
            this.distantModels = this.options.distantModels;
            Dep.prototype.setup.call(this);
        },

        data() {
          return _.extend(Dep.prototype.data.call(this), {
              columns: this.buildComparisonTableHeaderColumn()
          })
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, relationModel => {
                let modelRelationColumnId = this.scope.toLowerCase() + 'Id';
                let relationshipRelationColumnId = this.relationship.scope.toLowerCase() + 'Id';
                let relationFilter = {
                    maxSize: 500,
                    where: [
                        {
                            type: 'equals',
                            attribute: modelRelationColumnId,
                            value: this.model.id
                        }
                    ],
                };

                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }

                Promise.all([
                    this.ajaxGetRequest(this.scope + '/' + this.model.id + '/' + this.relationship.name, {
                        select: this.selectFields.join(',')
                    }),
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        'uri': this.scope + '/' + this.model.id + '/' + this.relationship.name + '?select=' + this.selectFields.join(','),
                        'type': 'list'
                    }),
                    this.ajaxGetRequest(this.relationName, relationFilter),
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        'uri': this.relationName + '?' + $.param(relationFilter),
                        'type': 'list'
                    }),
                ]).then(results => {
                    if (results[0].total > 500) {
                        this.hasToManyRecords = true;
                        callback();
                        return;
                    }

                    for (const result of results[1]) {
                        if (results.total > 500) {
                            this.hasToManyRecords = true;
                            callback();
                            return;
                        }
                    }

                    let entities = {};
                    results[0].list.forEach(item => {
                        item['isLocal'] = true;
                        entities[item.id] = item;
                    });

                    results[1].forEach((resultPerInstance,index) => {
                        return resultPerInstance.list.forEach(item => {
                            if (!entities[item.id]) {
                                item['isDistant'] = true;
                                entities[item.id] = this.setBaseUrlOnFile(item, index);
                            } else {
                                entities[item.id]['isDistant'] = true;
                            }
                        });
                    });

                    this.linkedEntities = Object.values(entities);

                    let relationEntities = [...results[2].list];
                    results[3].forEach((resultPerInstance, index) => {
                        if ('_error' in resultPerInstance) {
                            this.instances[index]['_error'] = resultPerInstance['_error'];
                        }
                        relationEntities.push(...resultPerInstance.list)
                    })
                    let models = [this.model, ...this.distantModels]

                    this.linkedEntities.forEach(item => {
                        this.relationModels[item.id] = [];
                        models.forEach((model, key) => {
                            let m = relationModel.clone()
                            m.set(this.isLinkedColumns, false);
                            relationEntities.forEach(relationItem => {
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

        },

        setBaseUrlOnFile(attr, index) {
            for (let key in attr) {
                let el = attr[key];
                let instanceUrl = this.instances[index].atrocoreUrl;
                if (key.includes('PathsData')) {
                    if (el && ('thumbnails' in el)) {
                        for (let size in el['thumbnails']) {
                            attr[key]['thumbnails'][size] = instanceUrl + '/' + attr['thumbnails'][size]
                        }
                    }
                }
            }
            return attr;
        },

        buildComparisonTableHeaderColumn() {
            let columns = [];
            columns.push({name: this.translate('instance', 'labels', 'Synchronization'), isFirst:true});
            columns.push({name: this.translate('current', 'labels', 'Synchronization')});
            this.instances.forEach(instance => {
                columns.push({
                    name: instance.name,
                    _error: instance._error
                })
            });
            return columns;
        },

        updateBaseUrl(view, instanceUrl) {
            if (Number.isInteger(instanceUrl)) {
                instanceUrl = this.instances[instanceUrl]?.atrocoreUrl;
            }
            view.listenTo(view, 'after:render', () => {
                setTimeout(() => {
                    let localUrl = this.getConfig().get('siteUrl');
                    view.$el.find('a').each((i, el) => {
                        let href = $(el).attr('href')

                        if (href.includes('http') && localUrl) {
                            $(el).attr('href', href.replace(localUrl, instanceUrl))
                        }

                        if ((!href.includes('http') && !localUrl) || href.startsWith('/#') || href.startsWith('?') || href.startsWith('#')) {
                            $(el).attr('href', instanceUrl + href)
                        }
                        $(el).attr('target', '_blank')
                    });

                    view.$el.find('img').each((i, el) => {
                        let src = $(el).attr('src')
                        if (src.includes('http') && localUrl) {
                            $(el).attr('src', src.replace(localUrl, instanceUrl))
                        }

                        if (!src.includes('http')) {
                            $(el).attr('src', instanceUrl + '/' + src)
                        }
                    });
                })
            }, 1000)
        }
    })
})