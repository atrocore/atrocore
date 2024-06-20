/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/fields-panels','view', function (Dep) {
    return Dep.extend({
        template: 'record/compare/fields-panels',
        fieldList: [],
        setup() {
            Dep.prototype.setup.call(this);
            this.scope = this.options.scope;
            this.fieldsArr = this.options.fieldsArr;
            this.wait(true);
            this.getHelper().layoutManager.get(this.scope, 'detail', layout => {
                if (layout && layout.length) {
                    this.fieldList = [];
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

                        this.fieldList.push(panelData);
                    });
                }
                this.setupFieldList();
                this.wait(false);
            });
        },
        data(){
            return {
                scope: this.scope,
                fieldList: this.fieldList,
                distantModels: this.options.distantModels
            }
        },
        setupFieldList(){
            this.fieldList.forEach((panel) => {

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
        }
    })
})