/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/script-preview', 'views/fields/base', Dep => {
    return Dep.extend({

        listTemplate: 'fields/field-value-container',

        detailTemplate: 'fields/field-value-container',

        editTemplate: 'fields/field-value-container',

        previewData: {},

        relatedScriptFieldName: 'script',

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.defs.fields[this.name] &&  this.model.defs.fields[this.name].multilangLocale) {
                let locale = this.model.defs.fields[this.name].multilangLocale;
                this.relatedScriptFieldName += locale.charAt(0).toUpperCase() + locale.charAt(1) + locale.charAt(3) + locale.charAt(4).toLowerCase();
            }

            this.preparePreview();
            this.listenTo(this.model, `change:${this.relatedScriptFieldName} change:outputType after:save`, () => {
                this.preparePreview();
            });

            this.listenTo(this.model, 'change:isMultilang change:type change:outputType', () => {
                this.controlViewVisibility();
            });
        },

        preparePreview() {
            if (this.model.isNew() || this.model.get('type') !== 'script') {
                return;
            }

            this.ajaxPostRequest('EntityField/action/renderScriptPreview', {
                scope: this.model.get('entityId'),
                field: this.model.get('code'),
                script: this.model.get(this.relatedScriptFieldName) || '',
                outputType: this.model.get('outputType'),
                id: `${this.model.get('entityId')}_${this.model.get('code')}`
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

            this.controlViewVisibility()
        },

        fetch() {
            return {};
        },

        controlViewVisibility() {
            let locale = this.model.getFieldParam(this.name, 'multilangLocale') || 'main';

            if (locale !== 'main') {
                if (this.model.get('type') === 'script' && this.model.get('isMultilang') && this.model.get('outputType') === 'text') {
                    this.show();
                } else {
                    this.hide();
                    this.model.set(this.name, null);
                }
            }
        }

    });

});
