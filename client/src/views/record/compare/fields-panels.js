/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/compare/fields-panels', 'views/record/base', function (Dep) {
    return Dep.extend({
        template: 'record/compare/fields-panels',

        fieldListPanels: [],

        instanceComparison: false,

        setup() {
            this.scope = this.options.scope;
            this.model = this.options.model;
            this.instances = this.options.instances ?? this.getMetadata().get(['app', 'comparableInstances'])
            this.instanceComparison = this.options.instanceComparison;
            this.columns = this.options.columns;

           this.fieldListPanels = [
               {
                   label: 'Fields',
                   fields:  this.options.fieldsArr
               }
           ]

            Dep.prototype.setup.call(this);

            this.setupFieldList();
        },

        data() {
            return {
                scope: this.scope,
                fieldList: this.fieldListPanels,
                columns: this.columns,
                columnLength: this.columns.length
            }
        },

        setupFieldList() {
            this.fieldListPanels.forEach((panel) => {
                panel.fields.forEach(fieldData => {
                    let field = fieldData.field;
                    let model = fieldData.modelCurrent;
                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(fieldData.type);
                    this.createView(field + 'Current', viewName, {
                        el: this.options.el + ` [data-field="${field}"]  .current`,
                        model: model,
                        readOnly: true,
                        defs: {
                            name: field,
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                    });

                    fieldData.modelOthers.forEach((model, index) => {
                        this.createView(field + 'Other' + index, viewName, {
                            el: this.options.el + ` [data-field="${field}"]  .other${index}`,
                            model: model,
                            readOnly: true,
                            defs: {
                                name: field
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        }, view => {
                            if (this.instanceComparison) {
                                view.listenTo(view, 'after:render', () => {
                                    let localUrl = this.getConfig().get('siteUrl');
                                    let instanceUrl = this.instances[index].atrocoreUrl;

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
                    })
                })
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this)
            $('.translated-automatically-field').hide();
            $('.not-approved-field').hide();
        }
    })
})