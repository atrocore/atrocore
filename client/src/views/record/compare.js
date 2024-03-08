/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
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
            this.distantModel = this.options.distantModel;
            this.scope = this.options.scope
            this.fields = this.getMetadata().get('entityDefs.'+this.scope+'.fields');
            this.links = this.getMetadata().get('entityDefs.'+this.scope+'.links');
        },
        data (){
            return {
                buttonList: this.buttonList,
                model: this.model,
                distantModel: this.distantModel,
                simpleFields: this.getNonLinkMultipleFields(),
                linkMultipleFields: this.linkMultipleFields(),
                scope: this.scope,
                id: this.id
            };
        },
        actionReset(){
            this.confirm(this.translate('confirmation', 'messages'), function () {

            }, this);
        },
        actionQuickCompare(data){
            this.getModelFactory().create(data.scope, function (model) {
                model.id = data.id;
                this.notify('Loading...');
                this.listenToOnce(model, 'sync', function () {
                    this.createView('dialog','views/compare',{
                        "model": model,
                        "scope": data.scope,
                        "hasModal": true
                    }, function(dialog){
                        dialog.render();
                        this.notify(false)
                        console.log('dialog','dialog')
                    })
                }, this);
                model.fetch({main: true});

                this.listenToOnce(this.baseController, 'action', function () {
                    model.abortLastFetch();
                }, this);
            }.bind(this));
        },
        getNonLinkMultipleFields(){
            let fields = [];
            for (const [key, value] of Object.entries(this.fields)) {
                 if(value.type !== 'linkMultiple' && !key.includes('__') ){
                     if(value.type === "link"){
                         fields.push({
                             "fieldName": key,
                             "isLink": true,
                             "entity": this.links[key].entity,
                             "current": {
                                 "id" :this.model.get(key+'Id'),
                                 "name": this.model.get(key+'Name')
                             },
                             "distant": {
                                 "id": this.distantModel[key+'Id'],
                                 "name": this.distantModel[key+'Name']
                             }
                         })
                     }else{
                         fields.push({
                             "fieldName": key,
                             "isLink": false,
                             "current": this.model.get(key),
                             "distant": this.distantModel[key],
                             areEquals: this.model.get(key) === this.distantModel[key]
                         })
                     }
                 }
            }
            return fields;
        },
        linkMultipleFields() {
            let fields = [];

            for (const [key, value] of Object.entries(this.fields)) {
                if(value.type === 'linkMultiple'){
                    fields.push({
                        "fieldName": key,
                        "type": value.type
                    })
                }
            }

            return fields;
        }
    });
});