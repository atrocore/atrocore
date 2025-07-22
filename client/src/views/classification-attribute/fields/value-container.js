/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/classification-attribute/fields/value-container', 'views/fields/base',
    (Dep) => Dep.extend({

        listTemplate: 'classification-attribute/fields/base',

        detailTemplate: 'classification-attribute/fields/base',

        editTemplate: 'classification-attribute/fields/base',

        fieldActions: false,

        setup() {
            this.name = this.options.name || this.defs.name;

            this.listenTo(this.model, 'change:attributeId', () => {
                if (this.mode === 'detail' || this.mode === 'edit') {
                    this.clearValue();

                    if (this.model.get('attributeId')) {
                        this.fetchAttributeData();
                    } else {
                        this.reRender();
                    }
                }
            });
        },

        clearValue() {
            this.model.set('value', undefined);
            this.model.set('valueCurrency', undefined);
            this.model.set('valueUnitId', undefined);
            this.model.set('valueAllUnits', undefined);
            this.model.set('valueId', undefined);
            this.model.set('valueName', undefined);
            this.model.set('valuePathsData', undefined);
            this.model.set('valueIds', undefined);
            this.model.set('valueNames', undefined);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();

            let hasDefault = this.mode === 'list';

            if (this.model.get('attributeType')){
                (this.getMetadata().get(`fields.${this.model.get('attributeType')}.params`) || []).forEach(item => {
                    if (item.name === 'default' || item.name === 'defaultDate'){
                        hasDefault = true;
                    }
                })
            }

            if (hasDefault) {
                this.$el.parent().show();
                let attributeType = this.model.get('attributeType');

                let fieldView = this.getFieldManager().getViewName(attributeType);

                let params = {
                    readOnly: !!this.model.get('isValueReadOnly'),
                    notNull: !!this.model.get('attributeNotNull'),
                    attributeName: this.model.get('attributeName')
                };

                if (this.model.name !== 'ClassificationAttribute') {
                    params.required = !!this.model.get('isRequired')
                }
                if (this.model.get('attributeIsMultilang')) {
                    params.multilangLocale = this.model.get('language')
                }

                if (this.getMetadata().get(['attributes', this.model.get('attributeType'), 'isValueReadOnly'])) {
                    params.readOnly = true;
                }

                if (this.model.get('maxLength')) {
                    params.maxLength = this.model.get('maxLength');
                    params.countBytesInsteadOfCharacters = this.model.get('countBytesInsteadOfCharacters');
                }

                this.model.defs['fields']['value']['min'] = undefined;
                if (this.model.get('min') !== null) {
                    params.min = this.model.get('min');
                    this.model.defs['fields']['value']['min'] = this.model.get('min');
                }

                this.model.defs['fields']['value']['max'] = undefined;
                if (this.model.get('max') !== null) {
                    params.max = this.model.get('max');
                    this.model.defs['fields']['value']['max'] = this.model.get('max');
                }

                if (this.model.get('useDisabledTextareaInViewMode')) {
                    params.useDisabledTextareaInViewMode = this.model.get('useDisabledTextareaInViewMode');
                }

                if (this.model.get('amountOfDigitsAfterComma')) {
                    params.amountOfDigitsAfterComma = this.model.get('amountOfDigitsAfterComma');
                }

                if (this.model.get('attributeMeasureId')) {
                    params.measureId = this.model.get('attributeMeasureId');
                    if (['int', 'float', 'varchar'].includes(attributeType)) {
                        fieldView = "views/fields/unit-" + attributeType;
                    }
                }

                if (this.model.get('attributeFileTypeId')) {
                    params.fileTypeId = this.model.get('attributeFileTypeId');
                    params.hideTypeField = !!params.fileTypeId;
                }

                const dropdownTypes = this.getMetadata().get(['app', 'attributeDropdownTypes'], {});
                if (this.model.get('attributeIsDropdown') && dropdownTypes[attributeType]) {
                    fieldView = dropdownTypes[attributeType];
                }

                let customOptions = {}
                if (attributeType === 'extensibleEnum' || attributeType === 'extensibleMultiEnum') {
                    params.prohibitedEmptyValue = !!this.model.get('prohibitedEmptyValue');
                    params.extensibleEnumId = this.model.get('attributeExtensibleEnumId');

                    customOptions = {
                        customSelectBoolFilters: ['onlyExtensibleEnumOptionIds'],
                        customBoolFilterData: {
                            onlyExtensibleEnumOptionIds() {
                                return this.model.get('extensibleEnumOptionsIds')
                            }
                        }
                    }
                }

                if (this.model.get('attributeType') === 'varchar') {
                    params.trim = this.model.get('attributeTrim');
                }

                let options = {
                    el: `${this.options.el} > .field[data-name="valueField"]`,
                    name: this.name,
                    model: this.model,
                    collection: this.model.collection || null,
                    params: params,
                    mode: this.mode,
                    inlineEditDisabled: true,
                    ...customOptions
                };

                if (attributeType === 'link') {
                    options.foreignScope = this.model.get('attributeEntityType');
                    options.params.foreignName = this.model.get('attributeEntityField');
                }
                this.createView('valueField', fieldView, options, view => {
                    view.render();

                    this.listenTo(this.model, 'change:extensibleEnumOptionsIds', () => {
                        if (attributeType === 'extensibleEnum' && !this.model.get('extensibleEnumOptionsIds').includes(this.model.get('value'))) {
                            try {
                                view.clearLink()
                            } catch (e) {
                            }
                        }

                        if (attributeType === 'extensibleMultiEnum') {
                            (this.model.get('value') ?? []).forEach(v => {
                                if (!this.model.get('extensibleEnumOptionsIds').includes(v)) {
                                    try {
                                        view.deleteLink(v)
                                    } catch (e) {
                                    }
                                }
                            })
                        }
                    });
                });

                if (this.mode === 'edit' && 'extensibleMultiEnum' === attributeType) {
                    this.$el.addClass('over-visible');
                }
            }
        },

        fetch() {
            let data = {};
            let view = this.getView('valueField');
            if (view) {
                _.extend(data, view.fetch());
                this.extendValueData(view, data);
            }
            return data;
        },

        extendValueData(view, data) {
            data = data || {};
            if (['file'].includes(view.type)) {
                _.extend(data, {
                    [this.name]: data[`${this.name}Id`]
                });
            }

            if (this.model.has('valueTranslateAutomatically')) {
                data['valueTranslateAutomatically'] = this.model.get('valueTranslateAutomatically');
                let $auto = this.$element.parent().find(`[data-parameter='auto']`);
                if ($auto.length > 0) {
                    data['valueTranslateAutomatically'] = $auto.is(":checked");
                }
            }

            if (this.model.has('valueTranslated')) {
                data['valueTranslated'] = this.model.get('valueTranslated');
                let $translated = this.$element.parent().find(`[data-parameter='translated']`);
                if ($translated.length > 0) {
                    data['valueTranslated'] = $translated.is(":checked");
                }
            }
        },

        validate() {
            let validate = false;
            let view = this.getView('valueField');
            if (view) {
                validate = view.validate();
            }
            return validate;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            let valueField = this.getView('valueField');
            if (valueField) {
                valueField.setMode(mode);
            }
        },

        fetchAttributeData() {
            this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attr => {
                this.model.set('attributeType', attr.type);
                this.model.set('attributeEntityType', attr.entityType);
                this.model.set('attributeEntityField', attr.entityField);
                this.model.set('attributeFileTypeId', attr.fileTypeId);
                this.model.set('attributeExtensibleEnumId', attr.extensibleEnumId);
                this.model.set('attributeIsDropdown', attr.dropdown);
                this.model.set('maxLength', attr.maxLength);
                this.model.set('attributeTrim', !!attr.trim);
                this.model.set('countBytesInsteadOfCharacters', attr.countBytesInsteadOfCharacters);
                this.model.set('useDisabledTextareaInViewMode', attr.useDisabledTextareaInViewMode);
                this.model.set('prohibitedEmptyValue', !!attr.prohibitedEmptyValue);
                this.model.set('attributeNotNull', !!attr.notNull);
                this.reRender();
            });
        }
    })
);

