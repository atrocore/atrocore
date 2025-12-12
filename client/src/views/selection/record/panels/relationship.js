/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/selection/record/panels/relationship', ['view', 'views/record/list'], function (Dep, List) {
    return Dep.extend({

        template: 'selection/record/panels/relationship',

        setup: function () {
            this.link = this.options.defs.name;
            this.scope = this.options.defs.scope;
            this.model = this.options.model;
            this.url = this.options.url;

            let relationName = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.link, 'relationName']);
            this.relationScope = relationName.charAt(0).toUpperCase() + relationName.slice(1);

            var url = this.url || this.model.name + '/' + this.model.id + '/' + this.link;

            this.wait(true);
            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = this.getConfig().get('maxSizeForEntityComparisons') || 50;
                collection.url = collection.urlRoot = url;
                if (this.options.defs.sortBy) {
                    collection.sortBy = sortBy;
                }
                if (this.options.defs.asc !== null) {
                    collection.asc = this.options.defs.asc;
                }

                collection.isRelation = true;
                const foreignLink = this.model.defs.links[this.link]?.foreign
                if (foreignLink) {
                    collection.whereForRelation = [
                        {
                            attribute: foreignLink,
                            type: 'linkedWith',
                            value: [this.model.id],
                        }
                    ]
                }

                this.collection = collection;

                this.getSelectAttributeList((selectAttributeList) => {
                    collection.data.select = selectAttributeList.join(',');
                    this.putAttributesToSelect();
                    collection.fetch({async: false}).then(() => {
                        if(collection.models.length === 0) {
                            this.wait(false);
                            return;
                        }
                        let count = 0;
                        collection.models.forEach(model => {
                            this.getRelationModel(model, (__) => {
                                model.defs['_relationName'] = this.link;
                                let detailLayout = this.buildElementLayout(model);
                                this.createView(model.id, 'views/selection/record/detail/detail-comparison-view', {
                                    model: model,
                                    el: this.options.el + ' .row[data-id="' + model.id + '"]',
                                    scope: model.name,
                                    detailLayout: detailLayout,
                                    name: model.id
                                });
                                count++;
                                if (count === collection.models.length) {
                                    this.wait(false);
                                }
                            })
                        });
                    })

                });

            }, this);
        },

        getRelationModel(model, callback) {
            if (model.get('__relationEntity')) {
                this.getModelFactory().create(this.relationScope, relModel => {
                    relModel.set(model.get('__relationEntity'));
                    model.relationModel = relModel
                    callback(relModel)
                })
            } else {
                callback(null)
            }
        },

        data() {
            return {
                rowList: this.collection.models.map(m => m.id)
            }
        },

        getSelectAttributeList(callback) {
            this._helper.layoutManager.get(this.scope, 'list', this.model.name + '.' + this.link, null, function (data) {
                this.layoutData = data
                this.internalLayout = data.layout;
                this.listLayout = this.filterListLayout(data.layout);
                callback(List.prototype.fetchAttributeListFromLayout.call(this));
            }.bind(this));
        },

        modifyAttributeList(attributeList) {
            return _.union(attributeList, this.getMetadata().get(['clientDefs', this.scope, 'additionalSelectAttributes']));
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
                    if (!this.relationScope || item.relEntity !== this.relationScope) {
                        listLayout.splice(item.number, 1);
                    }
                });
            }

            let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'read');
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

        buildElementLayout(model) {
            let layout = [
                {
                    label: "",
                    style: "",
                    rows: []
                }
            ];

            let displayedAttributes = [];
            for (const fieldData of this.internalLayout) {
                if (displayedAttributes.includes(fieldData.name)) {
                    continue;
                }
                displayedAttributes.push(fieldData.name);
                layout[0].rows.push([{
                    ...fieldData,
                    fullWidth: true
                }]);

                if (fieldData.attributeId) {
                    model.defs.fields[fieldData.name] = fieldData.attributeDefs;
                    model.defs.fields[fieldData.name].disableAttributeRemove = true;
                }
            }

            let relationName = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.link, 'relationName']);

            let relEntity = relationName.charAt(0).toUpperCase() + relationName.slice(1);

            $.each(this.getMetadata().get(['entityDefs', relEntity, 'fields']), (field, fieldDefs) => {
                if (fieldDefs.relationField || fieldDefs.readOnly || fieldDefs.layoutDetailDisabled || field === 'id') {
                    return;
                }
                let f = relEntity + '__' + field;
                if (displayedAttributes.includes(f)) {
                    return;
                }

                displayedAttributes.push(f);

                layout[0].rows.push([{
                    name: f,
                    fullWidth: true
                }])
            })


            return layout;
        }
    });
});
