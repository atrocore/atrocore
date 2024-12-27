/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/compare/fields-panels', 'view', function (Dep) {
    return Dep.extend({
        template: 'record/compare/fields-panels',

        fieldListPanels: [],

        instanceComparison: false,

        models: [],

        events: {
            'change input[type="radio"].field-radio': function (e) {
                e.stopPropagation();
                let modelId = e.currentTarget.value;
                let field = e.currentTarget.name;

                this.updateFieldState(field, modelId);
            },
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.scope = this.options.scope;
            this.model = this.options.model;
            this.instances = this.options.instances ?? this.getMetadata().get(['app', 'comparableInstances'])
            this.instanceComparison = this.options.instanceComparison;
            this.columns = this.options.columns;
            this.models = this.options.models;
            this.merging = this.options.merging;
            this.fieldList = this.options.fieldList;

            this.listenTo(this.model, 'select-model', (modelId) => {
                this.fieldList.forEach(fieldData => this.updateFieldState(fieldData.field, modelId));
            });
        },

        data() {
            return {
                scope: this.scope,
                fieldList: this.fieldList,
                columns: this.columns,
                merging: this.merging,
                columnLength: this.columns.length
            }
        },

        buildFieldViews() {
            this.fieldList.forEach(fieldData => {
                let field = fieldData.field;

                fieldData.fieldValueRows.forEach((row, index) => {
                    let model = this.models[index];
                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(fieldData.type);
                    this.createView(row.key, viewName, {
                        el: this.options.el + ` [data-field="${field}"]  .${row.class}`,
                        model: model.clone(),
                        defs: {
                            name: field
                        },
                        mode: (this.merging && index === 0)  ? 'edit' : 'detail',
                        inlineEditDisabled: true,
                    }, view => {
                        view.render();
                        if (this.instanceComparison && index !== 0) {
                            let instance = model.get('_instance');
                            view.listenTo(view, 'after:render', () => {
                                let localUrl = this.getConfig().get('siteUrl');
                                let instanceUrl = instance.atrocoreUrl;

                                view.$el.find('a').each((i, el) => {
                                    let href = $(el).attr('href')

                                    if (href.includes('http') && localUrl) {
                                        $(el).attr('href', href.replace(localUrl, instanceUrl))
                                    }

                                    if ((!href.includes('http') && !localUrl) || href.startsWith('/#') || href.startsWith('?') || href.startsWith('#')) {
                                        $(el).attr('href', instanceUrl + href)
                                    }
                                    $(el).attr('target', '_blank')
                                })
                                view.$el.find('img').each((i, el) => {
                                    let src = $(el).attr('src')
                                    if (src.includes('http') && localUrl) {
                                        $(el).attr('src', src.replace(localUrl, instanceUrl))
                                    }

                                    if (!src.includes('http')) {
                                        $(el).attr('src', instanceUrl + '/' + src)
                                    }
                                })
                            })
                        }
                    });
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this)
            this.buildFieldViews();
            if(this.merging) {
                $('input[data-id="' + this.models[0].id + '"]').prop('checked', true);
            }
        },

        updateFieldState(field, modelId) {
            let selectedIndex = this.models.findIndex(model => model.id === modelId);

            let fieldData = this.fieldList.find(el => el.field === field);
            fieldData.fieldValueRows.forEach( (row,index) =>{
                const view = this.getView(row.key);
                if(!view) {
                    return;
                }

                const mode = view.mode;

                if(selectedIndex === index){
                    view.setMode('edit');
                }else{
                    view.setMode('detail');
                }

                if(mode !== view.mode) {
                    view.model = this.models[index].clone();
                    view.reRender();
                }
            });
        }
    })
})