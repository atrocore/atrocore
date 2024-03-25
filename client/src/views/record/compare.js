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
        buttonList: [
            {
                name: 'Save',
                label: 'save',
                style: 'primary',
            },
            {
                name: 'Reset',
                label: 'reset',
                style: 'default',
            },
        ],
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
            this.distantModelAttribute = this.options.distantModelAttribute;
            this.scope = this.name =  this.options.scope
            this.links = this.getMetadata().get('entityDefs.'+this.scope+'.links');
            this.nonComparableFields = this.getMetadata().get('scopes.'+this.scope+'.nonComparableFields') ?? [];
            this.hideQuickMenu = this.options.hideQuickMenu
        },
        setup(){
            this.getModelFactory().create(this.scope, function (model) {
                var modelCurrent = this.model;
                var modelOther = model.clone();
                modelOther.set(this.distantModelAttribute);

                this.fieldsArr = [];

                let fieldDefs =  this.getMetadata().get(['entityDefs', this.scope, 'fields']) || {};

                Object.entries(fieldDefs).forEach(function ([field, fieldDef]) {
                    if(field === "data"){
                        return;
                    }

                    if(this.nonComparableFields.includes(field)){
                        return ;
                    }

                    let type = fieldDef['type'];

                    let fieldId = field;
                    if (type === 'asset' || type === 'link') {
                        fieldId = field + 'Id';
                    } else if (type === 'linkMultiple') {
                        fieldId = field + 'Ids';
                    }



                    if(!this.distantModelAttribute.hasOwnProperty(fieldId)){
                        return ;
                    }

                    if (model.getFieldParam(field, 'isMultilang') && !modelCurrent.has(fieldId) && !modelOther.has(fieldId)) {
                        return;
                    }


                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);
                    this.createView(field + 'Current', viewName, {
                        el: this.options.el + ' .current',
                        model: modelCurrent,
                        readOnly: true,
                        defs: {
                            name: field,
                            label: field + ' 11'
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                    });

                    this.createView(field + 'Other', viewName, {
                        el: this.options.el + ' .other',
                        model: modelOther,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                    });

                    let htmlTag = 'code';

                    if (type === 'color' || type === 'enum') {
                        htmlTag = 'span';
                    }

                    const isLink = type === 'link' || type === 'linkMultiple';
                    isLinkMultiple = type === 'linkMultiple';

                    const values = (isLinkMultiple && modelCurrent.get(fieldId)) ? modelCurrent.get(fieldId).map(v => {
                            return {
                                id:v,
                                name: modelCurrent.get(field+'Names') ? (modelCurrent.get(field+'Names')[v] ?? v) : v
                            }
                        }) : null;
                    this.fieldsArr.push({
                        isField: true,
                        field: field,
                        label:fieldDef['label'] ?? field,
                        current: field + 'Current',
                        htmlTag: htmlTag,
                        other: field + 'Other',
                        isLink: isLink && this.hideQuickMenu !== true,
                        foreignScope: isLink ? this.links[field].entity : null,
                        foreignId: isLink ? modelCurrent.get(fieldId)?.toString() : null,
                        isLinkMultiple: isLinkMultiple,
                        values: values,
                        different:  !this.areEquals(modelCurrent, modelOther, field, fieldDef)
                    });

                }, this);

                this.wait(false);

            }, this)
        },
        data (){
            return {
                buttonList: this.buttonList,
                fieldsArr: this.fieldsArr,
                distantModel: this.distantModelAttribute,
                scope: this.scope,
                id: this.id
            };
        },
        actionReset(){
            this.confirm(this.translate('confirmation', 'messages'), function () {

            }, this);
        },
        actionQuickCompare(data){

            this.notify('Loading...');

            this.ajaxGetRequest(this.generateEntityUrl(data.scope, data.id), {}, {async: false}).success(res => {
                const modalAttribute = res.list[0];
                modalAttribute['_fullyLoaded'] = true;

                this.getModelFactory().create(data.scope, function (model) {
                    model.id = data.id;
                    model.set(modalAttribute)
                    this.createView('dialog','views/modals/compare',{
                        "model": model,
                        "scope": data.scope,
                        "mode":"details"
                    }, function(dialog){
                        dialog.render();
                        this.notify(false)
                        console.log('dialog','dialog')
                    })
                }, this);
            }, this);

        },
        areEquals(current, other, field, fieldDef){
            if(fieldDef['type'] === 'linkMultiple'){
                const fieldId = field+'Ids';
                const fieldName = field+'Names'


                if((current.get(fieldId) && current.get(fieldId).length === 0) && (other.get(fieldId) && other.get(fieldId).length === 0)){
                    return  true;
                }

                return current.get(fieldId)?.toString() === other.get(fieldId)?.toString()
               && current.get(fieldName)?.toString() === other.get(fieldName)?.toString();
            }

            if(fieldDef['type'] === 'link'){
                const fieldId = field+'Id';
                const fieldName = field+'Name'
                return current.get(fieldId) === other.get(fieldId) && current.get(fieldName) === other.get(fieldName);
            }

            return current.get(field)?.toString() === other.get(field)?.toString()

        },
        afterRender(){
            this.$el.find('.list-row.different');
        }
    });
});