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

Espo.define('views/admin/field-manager/fields/script/preview', 'views/fields/base', Dep => {
    return Dep.extend({

        listTemplate: 'fields/field-value-container',

        detailTemplate: 'fields/field-value-container',

        editTemplate: 'fields/field-value-container',

        previewData: {},

        relatedScriptFieldName: 'script',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.name = this.options.name || this.defs.name;
            this.scope = this.options.scope || this.model.name;

            if (this.params.language) {
                let locale = this.params.language;

                this.relatedScriptFieldName += locale.charAt(0).toUpperCase() + locale.charAt(1) + locale.charAt(3) + locale.charAt(4).toLowerCase();
            }

            this.preparePreview();
            this.listenTo(this.model, `change:${this.relatedScriptFieldName} change:outputType after:save`, () => {
                this.preparePreview();
            });
        },

        preparePreview() {
            if (this.model.get('type') !== 'script') {
                return;
            }

            this.ajaxPostRequest('FieldManager/action/renderScriptPreview', {
                scope: this.scope,
                field: this.name,
                script: this.model.get(this.relatedScriptFieldName) || '',
                outputType: this.model.get('outputType'),
                id: this.model.get('id')
            }).then(res => {
                this.previewData = res;
                this.model.set(this.name, res.preview);
                this.reRender();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.previewData.outputType) {
                let fieldView = this.getFieldManager().getViewName(this.previewData.outputType);

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

            if (this.previewData.entity && this.previewData.entityType) {
                let name = this.previewData.entity.name || this.previewData.entity.id;
                this.$el.parent().find('label').html(`${this.translate('previewFor')} <a href="/#${this.previewData.entityType}/view/${this.previewData.entity.id}" target="_blank">${name}</a>`);
            }
        },

        fetch() {
            return {};
        },

    });

});
