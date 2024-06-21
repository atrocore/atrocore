/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationship','view', function (Dep) {
    return Dep.extend({
        template: 'record/compare/relationship',
        relationshipsFields: [],
        instanceNames: [],
        currentItemModels: [],
        otherItemModels: [],
        setup() {
            this.scope = this.options.scope;
            this.baseModel = this.options.model;
            this.relationship = this.options.relationship;
            this.fields = [];
            this.wait(true)

            this.getHelper().layoutManager.get(this.relationship.scope, 'listSmall', layout => {
                if (layout && layout.length) {
                    let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.relationship.scope, 'read');
                    layout.forEach(item => {
                        if (item.name && !forbiddenFieldList.includes(item.name)) {
                            this.fields.push(item.name);
                        }
                    });
                    this.getModelFactory().create(this.relationship.scope, function (model) {
                        model.scope = this.relationship.scope;
                        this.ajaxGetRequest(this.scope+'/'+this.model.get('id')+'/'+this.relationship.name, {
                            select: this.fields.join(',')
                        }).success(res => {
                            this.currentItemModels = res.list.map( item => {
                                let itemModel = model.clone()
                                itemModel.set(item)
                                return itemModel
                            });
                            this.ajaxGetRequest('Synchronization/action/distantInstanceRequest',{
                                'uri':this.scope+'/' + this.model.get('id')+'/' + this.relationship.name + '?select=' + this.fields.join(',')
                            }).success(res => {
                                this.otherItemModels = res.map( data => data.list.map(item => {
                                    let itemModel = model.clone()
                                    itemModel.set(item)
                                    return itemModel
                                }));
                                this.instanceNames = res.map(data => data['_connection']);
                                this.setupRelationship(() => this.wait(false));
                            })
                        });
                    }, this);
                }
            });
        },
        data(){
            return {
                name: this.relationship.name,
                scope: this.relationship.scope,
                instanceNames: this.instanceNames,
                relationshipsFields: this.relationshipsFields,
                columnCountCurrent: this.currentItemModels.length
            }
        },
        setupRelationship(callback){
            this.relationshipsFields = [];
            this.fields.forEach((field) => {
                let data = {
                    field,
                    currentViewKeys: [],
                    othersModelsKeyPerInstances: []
                }
                this.currentItemModels.forEach((model, index) => {
                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field));
                    let viewKey = this.relationship.name + field + index + 'Current';
                    data.currentViewKeys.push({key: viewKey})
                    this.createView(viewKey, viewName,  {
                        el: this.options.el +` [data-field="${viewKey}"]`,
                        model: model,
                        readOnly: true,
                        defs: {
                            name: field,
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                    });
                })

                this.otherItemModels.forEach((instanceModels, index1) => {
                    data.othersModelsKeyPerInstances[index1]= [];
                    instanceModels.forEach((model, index2) => {
                        this.others
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field));
                        let viewKey = this.relationship.name + field + index1 + 'Others' + index2;
                        data.othersModelsKeyPerInstances[index1].push({key: viewKey})
                        this.createView(viewKey, viewName,  {
                            el: this.options.el +` [data-field="${viewKey}"]`,
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
                this.relationshipsFields.push(data);
            });
            // debugger
            callback();
        }
    })
})