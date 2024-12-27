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
            this.maxTobeshown = 250;
            Dep.prototype.setup.call(this);
        },

        prepareModels(callback) {
            this.getModelFactory().create(this.relationName, relationModel => {
                let modelRelationColumnId = this.getModelRelationColumnId();
                let relationshipRelationColumnId = this.getRelationshipRelationColumnId();
                let relationFilter = {
                    maxSize: this.maxTobeshown,
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
                    this.ajaxGetRequest(this.scope + '/' + this.model.id + '/' + this.getLinkName(), {
                        select: this.selectFields.join(','),
                        maxSize: this.maxTobeshown
                    }),
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        'uri': this.scope + '/' + this.model.id + '/' + this.getLinkName() + '?select=' + this.selectFields.join(','),
                        'type': 'list'
                    }),
                    this.ajaxGetRequest(this.relationName, relationFilter),
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        'uri': this.relationName + '?' + $.param(relationFilter),
                        'type': 'list'
                    }),
                ]).then(results => {
                    if (results[0].total > this.maxTobeshown) {
                        this.hasToManyRecords = true;
                        callback();
                        return;
                    }

                    for (const result of results[1]) {
                        if (results.total > this.maxTobeshown) {
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

                    results[1].forEach((resultPerInstance, index) => {
                        return resultPerInstance.list.forEach(item => {
                            if (!entities[item.id]) {
                                item['isDistant'] = true;
                                item['instance'] = this.instances[index];
                                entities[item.id] = this.setBaseUrlOnFile(item, index);
                            } else {
                                entities[item.id]['isDistant'] = true;
                            }
                        });
                    });

                    this.linkedEntities = Object.values(entities);

                    let allRelationEntities = results[3].map(item => item.list);

                    allRelationEntities.unshift(results[2].list);

                    this.linkedEntities.forEach(entity => {
                        this.relationModels[entity.id] = [];
                        allRelationEntities.forEach(relationList => {
                            let m = relationModel.clone();
                            m.set(this.isLinkedColumns, false);
                            let relData = relationList.find(relationItem => entity.id === relationItem[relationshipRelationColumnId] && this.model.id === relationItem[modelRelationColumnId]);
                            if (relData) {
                                m.set(relData);
                                m.set(this.isLinkedColumns, true);
                            }
                            this.relationModels[entity.id].push(m);
                        });
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
                            attr[key]['thumbnails'][size] = instanceUrl + '/' + attr[key]['thumbnails'][size]
                        }
                    }
                }
            }
            return attr;
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
        },

        getFieldColumns(linkEntity) {
            let data = [];
            let baseUrl = !linkEntity.isLocal ? linkEntity.instance.atrocoreUrl : '';
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
<a href="${baseUrl}?entryPoint=download&id=${linkEntity.id}" download="" title="Download">
 <span class="glyphicon glyphicon-download-alt small"></span>
 </a> 
 <a href="${baseUrl}/#File/view/${linkEntity.id}" title="${linkEntity.name}" target="_blank" class="link" data-id="${linkEntity.id}">${linkEntity.name}</a>
 </div>`);
                        })
                    });
                });
            } else {
                data.push({
                    field: this.isLinkedColumns,
                    title: linkEntity.name,
                    label: `<a href="${baseUrl}#/${this.relationship.scope}/view/${linkEntity.id}"> ${linkEntity.name ?? linkEntity.id} </a>`,
                    entityValueKeys: []
                });
            }

            return data;
        }
    })
})