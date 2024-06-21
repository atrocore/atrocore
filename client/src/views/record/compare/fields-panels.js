/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/fields-panels','views/record/base', function (Dep) {
    return Dep.extend({
        template: 'record/compare/fields-panels',
        fieldListPanels: [],
        setup() {
            this.scope = this.options.scope;
            this.fieldsArr = this.options.fieldsArr;
            this.model = this.options.model;
            this.wait(true);
            this.getHelper().layoutManager.get(this.scope, 'detail', layout => {
                if (layout && layout.length) {
                    this.fieldListPanels = [];
                    let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'read');

                    layout.forEach(panel => {
                        let panelData = {
                            label: panel.label || panel.customLabel,
                            fields: []
                        };

                        (panel.rows || []).forEach(row => (row || []).forEach(item => {
                            if (item.name && !forbiddenFieldList.includes(item.name)) {
                                let field =  this.fieldsArr.filter( f => f.field === item.name)[0]
                                if(field){
                                    panelData.fields.push(field);
                                }
                            }
                        }));

                        this.fieldListPanels.push(panelData);
                    });
                }
                Dep.prototype.setup.call(this);
                this.setupFieldList();
                this.setupBeforeFinal();
                this.wait(false);
            });
        },
        data(){
            return {
                scope: this.scope,
                fieldList: this.fieldListPanels,
                distantModels: this.options.distantModels
            }
        },
        setupFieldList(){
            this.fieldListPanels.forEach((panel) => {
                panel.fields.forEach(fieldData => {
                    let field = fieldData.field;
                    let model = fieldData.modelCurrent;
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(fieldData.type);
                        this.createView(field + 'Current', viewName, {
                            el: this.options.el +` [data-field="${field}"]  .current`,
                            model: model,
                            readOnly: true,
                            defs: {
                                name: field,
                                label: field + ' 11'
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        });

                    fieldData.modelOthers.forEach((model, index) => {
                        this.createView(field + 'Other'+index, viewName, {
                            el: this.options.el+` [data-field="${field}"]  .other${index}`,
                            model: model,
                            readOnly: true,
                            defs: {
                                name: field
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        });
                    })
                })
            })
        },
        setupBeforeFinal(){
            this.uiHandlerDefs = _.extend(this.getMetadata().get('clientDefs.' + this.model.name + '.uiHandler') || [], this.uiHandler);
            this.initUiHandler();
        },
        hideField: function (name, locked) {

            this.recordHelper.setFieldStateParam(name, 'hidden', true);
            if (locked) {
                this.recordHelper.setFieldStateParam(name, 'hiddenLocked', true);
            }

            var processHtml = function () {
                var fieldView = this.getFieldView(name+'Current');
                    if(name === 'scriptDeDe'){
                        debugger
                    }
                if (fieldView) {
                    fieldView.$el.parent().addClass('hidden')
                } else {
                    let row = this.$el.find('.list-row[data-field="' + name + '"]');
                    if(row){
                        row.addClass('hidden')
                    }
                }
            }.bind(this);
            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                view.setDisabled(locked);
            }
        },

    })
})