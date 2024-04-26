/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/admin/field-manager/fields/linkMultiple/extensible-enum-options', ['views/fields/base', 'view-record-helper'], (Dep, ViewRecordHelper) => {

    return Dep.extend({

        listTemplate: 'fields/field-value-container',
        detailTemplate: 'fields/field-value-container',
        editTemplate: 'fields/field-value-container',

        setup() {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:extensibleEnumId', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.hide();
            this.$el.parent().find('label[data-name="extensibleEnumOptions"]').remove();

            if (this.model.get('extensibleEnumId')) {
                let scope = 'ExtensibleEnum';
                this.getModelFactory().create(scope, model => {
                    model.set('id', this.model.get('extensibleEnumId'));
                    model.fetch().success(() => {
                        let options = {
                            el: `${this.options.el} > .field[data-name="valueField"]`,
                            model: model,
                            scope: scope,
                            staticAllowedPanelNames: ["extensibleEnumOptions"],
                            recordHelper: new ViewRecordHelper(),
                            canClose: false
                        };

                        this.createView('valueField', 'views/record/detail-bottom', options, view => {
                            view.render();
                            this.listenTo(view.model,'prepareAttributesForCreateRelated', function(params, link, prepareAttributeCallback){
                                  prepareAttributeCallback({
                                      "listMultilingual": view.model.get('multilingual')
                                  })
                            })
                            this.show();
                        });
                    });
                });
            }
        },

    });
});