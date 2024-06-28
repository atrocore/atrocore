/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare','view', function (Dep) {

    return Dep.extend({
        template: 'record/compare',
        panelDetailNavigation: null,
        fieldsPanelsView: 'views/record/compare/fields-panels',
        relationshipsPanelsView: 'views/record/compare/relationships-panels',
        buttonList: [],
        fieldsArr: [],
        events: {
            'click .button-container .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    if (typeof this[method] == 'function') {
                        this[method].call(this, data, e);
                        e.preventDefault();
                    }
                }
            },
            'click .dropdown-menu .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    if (typeof this[method] == 'function') {
                        this[method].call(this, data, e);
                        e.preventDefault();
                    }
                }
            }
        },
        init(){
            Dep.prototype.init.call(this);
            this.id = this.model.get('id');
            this.distantModelsAttribute = this.options.distantModelsAttribute;
            this.scope = this.name =  this.options.scope
            this.links = this.getMetadata().get('entityDefs.'+this.scope+'.links');
            this.nonComparableFields = this.getMetadata().get('scopes.'+this.scope+'.nonComparableFields') ?? [];
            this.hideQuickMenu = this.options.hideQuickMenu;


        },
        setup(){
            this.instances = this.getMetadata().get(['app','comparableInstances'])
            this.notify('Loading...')
            this.getModelFactory().create(this.scope, function (model) {
                let modelCurrent = this.model;
                let  modelOthers = [];
                this.distantModelsAttribute.forEach((modelAttribute, index) => {

                    if('_error' in modelAttribute){
                        this.instances[index]['_error'] = modelAttribute['_error'];
                    }
                    let  m = model.clone();
                    for(let key in modelAttribute){
                        let el = modelAttribute[key];
                        let instanceUrl = this.instances[index].atrocoreUrl;
                        if(key.includes('PathsData')){
                            if( el && ('thumbnails' in el)){
                                for (let size in el['thumbnails']){
                                    modelAttribute[key]['thumbnails'][size] = instanceUrl + '/' + el['thumbnails'][size]
                                }
                            }
                        }
                    }
                    m.set(modelAttribute);
                    modelOthers.push(m);
                })

                this.fieldsArr = [];

                let fieldDefs =  this.getMetadata().get(['entityDefs', this.scope, 'fields']) || {};

                Object.entries(fieldDefs).forEach(function ([field, fieldDef]) {

                    if(this.nonComparableFields.includes(field)){
                        return ;
                    }

                    if(field.includes('_')){
                        return ;
                    }

                    const  type = fieldDef['type'];
                    const isLink = type === 'link' || type === 'linkMultiple';

                    if( isLink && !this.links[field]?.entity){
                        return;
                    }

                    let fieldId = field;
                    if (type === 'asset' || type === 'link') {
                        fieldId = field + 'Id';
                    } else if (type === 'linkMultiple') {
                        fieldId = field + 'Ids';
                    }

                    if (model.getFieldParam(field, 'isMultilang')
                        && !modelCurrent.has(fieldId)
                        && !modelOthers.map(m => m.has(fieldId)).reduce((previous, current) => previous || current)) {
                        return;
                    }

                    let htmlTag = 'code';

                    if (type === 'color' || type === 'enum') {
                        htmlTag = 'span';
                    }

                    isLinkMultiple = type === 'linkMultiple';

                    const values = (isLinkMultiple && modelCurrent.get(fieldId)) ? modelCurrent.get(fieldId).map(v => {
                            return {
                                id:v,
                                name: modelCurrent.get(field+'Names') ? (modelCurrent.get(field+'Names')[v] ?? v) : v
                            }
                        }) : null;

                    let showDetailsComparison = (modelCurrent.get(fieldId)  && type === "link")
                        || ((modelCurrent.get(fieldId)?.length ?? 0) > 0  && type === "linkMultiple")
                    if(showDetailsComparison){
                        for (const other of modelOthers) {
                            showDetailsComparison = showDetailsComparison && modelCurrent.get(fieldId)?.toString() === other.get(fieldId)?.toString();
                        }
                    }

                    this.fieldsArr.push({
                        isField: true,
                        field: field,
                        type: type,
                        label: fieldDef['label'] ?? field,
                        current: field + 'Current',
                        modelCurrent: modelCurrent,
                        modelOthers: modelOthers,
                        htmlTag: htmlTag,
                        others: modelOthers.map((element, index) => {
                            return  {other: field + 'Other'+index, index}
                        }),
                        isLink: isLink ,
                        foreignScope: isLink ? this.links[field].entity : null,
                        foreignId: isLink ? modelCurrent.get(fieldId)?.toString() : null,
                        showDetailsComparison: showDetailsComparison && this.hideQuickMenu !== true,
                        isLinkMultiple: isLinkMultiple,
                        values: values,
                        different:  !this.areEquals(modelCurrent, modelOthers, field, fieldDef),
                        required: !!fieldDef['required']
                    });

                }, this);

                this.afterModelsLoading(modelCurrent, modelOthers);
                this.listenTo(this, 'after:render', () => {
                    this.setupFieldsPanels();
                    if(this.options.hideRelationShip !== true){
                        this.setupRelationshipsPanels()
                    }
                });
            }, this)

        },
        setupFieldsPanels(){
            this.notify('Loading...')
            this.createView('fieldsPanels', this.fieldsPanelsView, {
                scope: this.scope,
                model: this.model,
                fieldsArr: this.fieldsArr,
                instances: this.instances,
                distantModels: this.distantModelsAttribute,
                el: `${this.options.el} .compare-panel[data-name="fieldsPanels"]`
            }, view => {
                view.render();
                this.notify(false);
            })
        },

        setupRelationshipsPanels(){
            this.notify('Loading...')

            this.getHelper().layoutManager.get(this.scope, 'relationships', layout => {
                this.createView('relationshipsPanels', this.relationshipsPanelsView, {
                    scope: this.scope,
                    model: this.model,
                    relationships: layout,
                    distantModels: this.distantModelsAttribute,
                    el: `${this.options.el} .compare-panel[data-name="relationshipsPanels"]`
                }, view => {
                    this.notify(false)
                    view.render();
                })
            });
        },
        data (){
            return {
                buttonList: this.buttonList,
                fieldsArr: this.fieldsArr,
                instances: this.instances,
                scope: this.scope,
                id: this.id
            };
        },
        actionReset(){
            this.confirm(this.translate('confirmation', 'messages'), function () {

            }, this);
        },

        areEquals(current, others, field, fieldDef){
            if(fieldDef['type'] === 'linkMultiple'){
                const fieldId = field+'Ids';
                const fieldName = field+'Names'

                if(
                    (current.get(fieldId) && current.get(fieldId).length === 0)
                    && others.map(other =>(other.get(fieldId) && other.get(fieldId).length === 0)).reduce((prev, curr) => prev && curr))
                {
                    return  true;
                }

                result = true;
                for (const other of others) {
                    result = result && current.get(fieldId)?.toString() === other.get(fieldId)?.toString()
                        && current.get(fieldName)?.toString() === other.get(fieldName)?.toString();
                }
                return result
            }

            if(fieldDef['type'] === 'link'){
                const fieldId = field+'Id';
                const fieldName = field+'Name'
                result = true;

                for (const other of others) {
                    result = result && current.get(fieldId) === other.get(fieldId) && current.get(fieldName) === other.get(fieldName);
                }

                return result;
            }

            result = true;
            for (const other of others) {
                result = result && current.get(field)?.toString() === other.get(field)?.toString();
            }
            return result;

        },
        afterRender(){
           this.notify(false)
        },
        afterModelsLoading(modelCurrent, modelOthers){},

        actionDetailsComparison(data){
            this.notify('Loading...');
            this.getModelFactory().create(data.scope, (model) => {
                model.id = data.id;
                this.listenToOnce(model, 'sync', function () {
                    this.createView('dialog','views/modals/compare',{
                        "model": model,
                        "scope": data.scope,
                        "mode":"details",
                    }, function(dialog){
                        dialog.render();
                        this.notify(false)
                    })
                }, this);
                model.fetch({main: true});
            });
        },
    });
});