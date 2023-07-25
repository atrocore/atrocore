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

Espo.define('views/admin/field-manager/fields/script/preview', 'views/fields/base', Dep => {
    return Dep.extend({

        listTemplate: 'fields/field-value-container',

        detailTemplate: 'fields/field-value-container',

        editTemplate: 'fields/field-value-container',

        currentEntity: null,

        scope: null,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.name = this.options.name || this.defs.name;
            this.scope = this.options.scope || this.model.name;

            if (this.model.name === 'Attribute') {
                this.scope = 'Product';
            }

            this.preparePreview();
            this.listenTo(this.model, 'change:script change:outputType', () => {
                this.preparePreview();
            });
        },

        preparePreview() {
            this.ajaxPostRequest('FieldManager/action/renderScriptPreview', {
                scope: this.scope,
                script: this.model.get('script') || '',
                outputType: this.model.get('outputType')
            }).then(res => {
                this.currentEntity = res.entity;
                this.model.set('preview', res.preview);
                this.reRender();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('outputType')) {
                let outputType = this.model.get('outputType');

                let fieldView = this.getFieldManager().getViewName(outputType);

                let params = {
                    required: false,
                    readOnly: true,
                    useDisabledTextareaInViewMode: true
                };

                let options = {
                    el: `${this.options.el} > .field[data-name="valueField"]`,
                    name: this.name,
                    model: this.model,
                    collection: this.model.collection || null,
                    params: params,
                    mode: this.mode,
                    inlineEditDisabled: true
                };

                this.createView('valueField', fieldView, options, view => {
                    view.render();
                });
            }

            if (this.currentEntity) {
                let name = this.currentEntity.name || this.currentEntity.id;
                this.$el.parent().find('label').html(`${this.translate('previewFor')} <a href="/#${this.scope}/view/${this.currentEntity.id}" target="_blank">${name}</a>`);
            }
        },

        fetch() {
            return {};
        },

    });

});
