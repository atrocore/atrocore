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
            this.setupBeforeFinal();
        },

        data() {
            return {
                scope: this.scope,
                fieldList: this.fieldListPanels,
                columns: this.columns,
                columnLength: this.columns.length + 1
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
                            label: field + ' 11'
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
                            view.render()
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
            })
        },
        setupBeforeFinal() {
            this.uiHandlerDefs = _.extend(this.getMetadata().get('clientDefs.' + this.model.name + '.uiHandler') || [], this.uiHandler);
            this.initUiHandler();
        },

        hideField: function (name, locked) {

            this.recordHelper.setFieldStateParam(name, 'hidden', true);
            if (locked) {
                this.recordHelper.setFieldStateParam(name, 'hiddenLocked', true);
            }

            var processHtml = function () {
                var fieldView = this.getFieldView(name + 'Current');
                if (fieldView) {
                    fieldView.$el.parent().addClass('hidden')
                } else {
                    let row = this.$el.find('.list-row[data-field="' + name + '"]');
                    if (row) {
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
        afterRender() {
            Dep.prototype.afterRender.call(this)
            $('.not-approved-field').hide();
        }
    })
})