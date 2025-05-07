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

Espo.define('views/fields/base', 'view', function (Dep) {

    return Dep.extend({

        type: 'base',

        listTemplate: 'fields/base/list',

        listLinkTemplate: 'fields/base/list-link',

        detailTemplate: 'fields/base/detail',

        editTemplate: 'fields/base/edit',

        searchTemplate: 'fields/base/search',

        validations: ['required'],

        name: null,

        measureId: null,

        defaultUnit: null,

        defs: null,

        params: null,

        mode: null,

        searchParams: null,

        _timeout: null,

        inlineEditDisabled: false,

        inheritanceActionDisabled: false,

        disabled: false,

        readOnly: false,

        attributeList: null,

        initialAttributes: null,

        VALIDATION_POPOVER_TIMEOUT: 3000,

        fieldActions: true,

        inlineEditModeIsOn: false,

        defaultFilterValue: null,


        translate: function (name, category, scope) {
            if (category === 'fields' && scope === this.model.name && this.model.getFieldParam(name, 'label')) {
                return this.model.getFieldParam(name, 'label');
            }

            return Dep.prototype.translate.call(this, name, category, scope);
        },

        isRequired: function () {
            return this.params.required;
        }, /**
         * Get cell element. Works only after rendered.
         * {jQuery}
         */
        getCellElement: function () {
            return this.$el.parent();
        },

        getStatusIconsContainer: function () {
            return this.getLabelElement().find('.status-icons');
        },

        getInlineActionsContainer: function () {
            return this.getCellElement().children('.inline-actions');
        },

        setDisabled: function (locked) {
            this.disabled = true;
            if (locked) {
                this.disabledLocked = true;
            }
        },

        setNotDisabled: function () {
            if (this.disabledLocked) return;
            this.disabled = false;
        },

        setRequired: function () {
            this.params.required = true;

            if (this.isRendered()) {
                this.showRequiredSign();
            } else {
                this.once('after:render', function () {
                    this.showRequiredSign();
                }, this);
            }
        },

        setNotRequired: function () {
            this.params.required = false;
            this.getCellElement().removeClass('has-error');

            if (this.mode === 'edit') {
                if (this.isRendered()) {
                    this.hideRequiredSign();
                } else {
                    this.once('after:render', function () {
                        this.hideRequiredSign();
                    }, this);
                }
            }
        },

        setReadOnly: function (locked) {
            if (this.readOnlyLocked) return;
            this.readOnly = true;
            if (locked) {
                this.readOnlyLocked = true;
            }
            if (this.mode == 'edit') {
                this.setMode('detail');
                if (this.isRendered()) {
                    this.reRender();
                }
            }
        },

        setNotReadOnly: function () {
            if (this.readOnlyLocked) return;
            this.readOnly = false;
        }, /**
         * Get label element. Works only after rendered.
         * {jQuery}
         */
        getLabelElement: function () {
            if (!this.$label || !this.$label.size()) {
                this.$label = this.$el.parent().children('label');
            }
            return this.$label;
        }, /**
         * Hide field and label. Works only after rendered.
         */
        hide: function () {
            this.$el.addClass('hidden');
            var $cell = this.getCellElement();
            $cell.children('label').addClass('hidden');
            $cell.addClass('hidden-cell');
        }, /**
         * Show field and label. Works only after rendered.
         */
        show: function () {
            this.$el.removeClass('hidden');
            var $cell = this.getCellElement();
            $cell.children('label').removeClass('hidden');
            $cell.removeClass('hidden-cell');
        },

        data: function () {
            var data = {
                scope: this.model.name,
                name: this.name,
                defs: this.defs,
                params: this.params,
                value: this.getValueForDisplay(),
                isNull: this.model.get(this.name) === null || this.model.get(this.name) === undefined
            };
            if (this.mode === 'search') {
                data.searchParams = this.searchParams;
                data.searchData = this.searchData;
                data.searchValues = this.getSearchValues();
                data.searchType = this.getSearchType();
                data.searchTypeList = this.getSearchTypeList();
            }

            return data;
        },

        getValueForDisplay: function () {
            return this.model.get(this.name);
        },

        setMode: function (mode) {
            this.mode = mode;
            var property = mode + 'Template';
            if (!(property in this)) {
                this[property] = 'fields/' + Espo.Utils.camelCaseToHyphen(this.type) + '/' + this.mode;
            }
            this.template = this[property];
            this.getCellElement()?.attr('data-mode', this.mode);
        },

        getTooltipText() {
            if (this.model.get('attributesDefs')) {
                return this.model.get('attributesDefs')[this.name]?.tooltipText;
            }

            const tooltipText = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'tooltipText']);
            const tooltipDefaultTranslate = this.translate(this.name, 'tooltips', this.model.name);
            const tooltipTextTranslate = this.translate(tooltipText, 'tooltips', this.model.name);
            let tooltipTextValue = null;

            if (tooltipText) {
                tooltipTextValue = tooltipTextTranslate;
            } else if (this.name !== (this.options.tooltipText || tooltipDefaultTranslate)) {
                tooltipTextValue = this.options.tooltipText || tooltipDefaultTranslate;
            }

            return tooltipTextValue;
        },

        init: function () {
            if (this.events) {
                this.events = _.clone(this.events);
            } else {
                this.events = {};
            }
            this.defs = this.options.defs || {};
            this.name = this.options.name || this.defs.name;
            this.params = this.options.params || this.defs.params || {};

            this.fieldType = this.model.getFieldParam(this.name, 'type') || this.type;

            this.getFieldManager().getParamList(this.type).forEach(function (d) {
                var name = d.name;
                if (!(name in this.params)) {
                    this.params[name] = this.model.getFieldParam(this.name, name) || null;
                }
            }, this);

            this.measureId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'measureId']) || this.model.getFieldParam(this.name, 'measureId');
            if (this.params.measureId) {
                this.measureId = this.params.measureId;
            }

            this.mode = this.options.mode || this.mode;

            this.readOnly = this.readOnly || this.name === 'id' || this.params.readOnly || this.model.getFieldParam(this.name, 'readOnly') || this.model.getFieldParam(this.name, 'clientReadOnly');
            this.readOnlyLocked = this.options.readOnlyLocked || this.readOnly;
            this.inlineEditDisabled = this.options.inlineEditDisabled || this.params.inlineEditDisabled || this.model.getFieldParam(this.name, 'inlineEditDisabled') || this.inlineEditDisabled;
            this.inheritanceActionDisabled = this.options.inheritanceActionDisabled || this.params.inheritanceActionDisabled || this.model.getFieldParam(this.name, 'inheritanceActionDisabled') || this.inheritanceActionDisabled;
            this.readOnly = this.readOnlyLocked || this.options.readOnly || false;

            this.tooltip = this.options.tooltip || this.params.tooltip || this.model.getFieldParam(this.name, 'tooltip') || (this.getMetadata().get(['entityDefs', this.model.urlRoot, 'fields', this.name, 'tooltipLink']));

            if (this.options.readOnlyDisabled) {
                this.readOnly = false;
            }

            this.disabledLocked = this.options.disabledLocked || false;
            this.disabled = this.disabledLocked || this.options.disabled || this.disabled;

            if (this.mode == 'edit' && this.readOnly) {
                this.mode = 'detail';
            }

            this.setMode(this.mode || 'detail');

            if (this.mode == 'search') {
                this.searchParams = _.clone(this.options.searchParams || {});
                this.searchData = {};
                this.setupSearch();
            }

            this.on('invalid', function () {
                var $cell = this.getCellElement();
                $cell.addClass('has-error');
                this.$el.one('click', function () {
                    $cell.removeClass('has-error');
                });
                this.once('render', function () {
                    $cell.removeClass('has-error');
                });
            }, this);

            this.on('after:render', function () {
                if (this.hasRequiredMarker()) {
                    this.showRequiredSign();
                } else {
                    this.hideRequiredSign();
                }

            }, this);

            if (this.mode === 'detail') {
                this.initInlineActions();
                this.initInheritanceActions();
            }

            if ((this.mode == 'detail' || this.mode == 'edit') && this.tooltip) {
                const tooltipLinkValue = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'tooltipLink']);
                const tooltipTextValue = this.getTooltipText();

                this.once('after:render', function () {
                    const label = this.getLabelElement().find('.label-text');
                    if (tooltipTextValue) {
                        label.attr('title', tooltipTextValue);
                    }

                    if (tooltipLinkValue) {
                        label.attr('data-title-link', tooltipLinkValue);
                    }
                }, this);
            }

            if (this.fieldActions) {
                (this.getMetadata().get('app.fieldActions') || []).forEach(item => {
                    this.createView(item.name, item.view, {
                        model: this.model,
                        name: this.name,
                        el: this.$el,
                        fieldView: this,
                    }, view => {
                        this.listenTo(this, 'after:render', () => {
                            view.initFieldActions();
                        });
                    });
                });
            }

            if (this.mode != 'search') {
                this.attributeList = this.getAttributeList();

                this.listenTo(this.model, 'change', function (model, options) {
                    if (this.isRendered() || this.isBeingRendered()) {
                        if (options.ui) {
                            return;
                        }

                        var changed = false;
                        this.attributeList.forEach(function (attribute) {
                            if (model.hasChanged(attribute)) {
                                changed = true;
                            }
                        });

                        if (changed) {
                            this.reRender();
                        }
                    }
                }.bind(this));

                this.listenTo(this, 'change', function () {
                    var attributes = this.fetch();
                    this.model.set(attributes, {ui: true});
                });
            }
        },

        initStatusContainer: function () {
            if (!['detail', 'edit'].includes(this.mode)) {
                return;
            }

            if (this.$el.parents('.stream-head-container').size() > 0) {
                return;
            }

            const label = this.getLabelElement();
            if (label.find('.status-icons').size() === 0) {
                label.append('<sup class="status-icons"></sup>');
            }

            if (this.getCellElement().children('.inline-actions').size() === 0) {
                this.getCellElement().prepend('<div class="pull-right inline-actions"></div>');
            }
        },

        initInlineActions: function () {
            this.listenTo(this, 'after:render', () => {
                this.initStatusContainer();
                this.initRemoveAttributeValue();
                this.initDynamicFieldActions();
                if (!this.inlineEditDisabled) {
                    this.initInlineEdit();
                }
            }, this);
        },

        initInheritanceActions: function () {
            if (!this.inheritanceActionDisabled) {
                this.listenTo(this, 'after:render', () => {
                    this.initStatusContainer();
                    this.initInheritedFieldMarker();
                }, this);
            }
        },


        showRequiredSign: function () {
            this.initStatusContainer();

            const statusIcons = this.getStatusIconsContainer();
            let $sign = statusIcons.find('.required-sign');

            if (statusIcons.size() && !$sign.size()) {
                statusIcons.prepend(`<i class="ph ph-asterisk required-sign pressable-icon" title="${this.translate('Required')}"></i>`);
                $sign = statusIcons.find('.required-sign');
                $sign.click(() => {
                    this.model.trigger('toggle-required-fields-highlight');
                });
            }

            $sign.show();
        },

        hideRequiredSign: function () {
            this.getStatusIconsContainer().find('.required-sign').hide();
        },

        getSearchParamsData: function () {
            return this.searchParams.data || {};
        },

        getSearchValues: function () {
            return this.getSearchParamsData().values || {};
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.type;
        },

        getSearchTypeList: function () {
            return this.searchTypeList;
        },

        getInheritedIconEl: function () {
            return this.getCellElement().find(`.info-field-icon.inherited[data-name="${this.name}"]`);
        },

        getInheritedIconHtml: function () {
            return `<i data-name="${this.name}" class="ph ph-link-simple-horizontal info-field-icon inherited" title="${this.translate('inherited')}"></i>`;
        },

        getNonInheritedIconEl: function () {
            return this.getCellElement().find(`.info-field-icon.not-inherited[data-name="${this.name}"]`);
        },

        getNonInheritedIconHtml: function () {
            return `<i data-name="${this.name}" class="ph ph-link-simple-horizontal-break info-field-icon not-inherited" title="${this.translate('notInherited')}"></i>`;
        },

        getInheritActionEl: function () {
            return this.getCellElement().find(`.lock-link[data-name="${this.name}"]`);
        },

        getNonInheritedFields: function () {
            const scope = this.model.urlRoot;

            let nonInheritedFields = this.getMetadata().get(`app.nonInheritedFields`) || [];

            (this.getMetadata().get(`scopes.${scope}.mandatoryUnInheritedFields`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`scopes.${scope}.unInheritedFields`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`app.nonInheritedRelations`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`scopes.${scope}.mandatoryUnInheritedRelations`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`scopes.${scope}.unInheritedRelations`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            $.each(this.getMetadata().get(`entityDefs.${scope}.links`), (link, linkDefs) => {
                if (linkDefs.type && linkDefs.type === 'hasMany') {
                    if (!linkDefs.relationName) {
                        nonInheritedFields.push(link);
                    }
                }
            });

            return nonInheritedFields;
        },

        isInheritableField: function () {
            if (!this.model.has('inheritedFields') || this.getMetadata().get(`scopes.${this.model.urlRoot}.fieldValueInheritance`) !== true) {
                return false;
            }

            const nonInheritedFields = this.getNonInheritedFields();

            return !nonInheritedFields.includes(this.name);
        },

        initInheritedFieldMarker: function () {
            const $cell = this.getCellElement();
            const scope = this.model.urlRoot;
            const type = this.getMetadata().get(`entityDefs.${scope}.fields.${this.name}.type`);

            if (['enum', 'multiEnum'].includes(type) && this.getMetadata().get(`entityDefs.${scope}.fields.${this.name}.multilangLocale`)) {
                return;
            }

            this.getInheritedIconEl().remove();
            this.getNonInheritedIconEl().remove();
            this.getInheritActionEl().remove();

            if (!this.isInheritableField() || this.model.get('isRoot') === true || this.readOnly === true) {
                return;
            }

            if (this.getInheritedIconEl().length === 0 && this.isInheritedField()) {
                this.getStatusIconsContainer().append(this.getInheritedIconHtml());
                return;
            }

            if (this.getNonInheritedIconEl().length === 0 && !this.isInheritedField()) {
                this.getStatusIconsContainer().append(this.getNonInheritedIconHtml());
                this.getInlineActionsContainer().append(`<a class="action lock-link hidden" href="javascript:" data-name="${this.name}" data-action="setAsInherited" title="${this.translate('setAsInherited')}"><i class="ph ph-link-simple-horizontal"></i></a>`);
            }

            $cell.on('mouseenter', function (e) {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    this.getInheritActionEl().removeClass('hidden');
                }
            }.bind(this)).on('mouseleave', function (e) {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    this.getInheritActionEl().addClass('hidden');
                }
            }.bind(this));
        },

        initInlineEdit: function () {
            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.inline-edit').parent().remove();

            const $editLink = $(`<a href="javascript:" class="inline-edit-link hidden" title="${this.translate('Edit')}"><i class="ph ph-pencil-simple-line inline-edit"></i></a>`);

            if (inlineActions.size()) {
                inlineActions.prepend($editLink);
            } else {
                $cell.prepend($editLink);
            }

            $editLink.on('click', function () {
                this.inlineEdit();
            }.bind(this));

            $cell.on('mouseenter', function (e) {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode == 'detail') {
                    $editLink.removeClass('hidden');
                }
            }.bind(this)).on('mouseleave', function (e) {
                e.stopPropagation();
                if (this.mode == 'detail') {
                    $editLink.addClass('hidden');
                }
            }.bind(this));
        },

        getRecordView() {
            return this.getParentView()?.getParentView();
        },

        initDynamicFieldActions() {
            const recordView = this.getRecordView();
            let dynamicActions = recordView?.dynamicFieldActions || [];
            dynamicActions = dynamicActions.filter(action => action.displayField === this.name)

            if (!dynamicActions.length) {
                return;
            }

            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.dynamic-action').remove();

            dynamicActions.forEach(action => {
                const $button = $(`<a href="javascript:" class="dynamic-action hidden" style="margin-left: 3px" title="${action.label}">${action.label}</a>`);

                if (inlineActions.size()) {
                    inlineActions.prepend($button);
                } else {
                    $cell.prepend($button);
                }

                $button.on('click', () => {
                    recordView.actionDynamicAction({id: action.data.action_id})
                });
            })


            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    $cell.find('.dynamic-action').removeClass('hidden');
                }
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $cell.find('.dynamic-action').addClass('hidden');
                }
            });
        },

        initRemoveAttributeValue() {
            const fieldName = this.originalName || this.name;

            if (!this.model.get('attributesDefs') || !fieldName || !this.model.get('attributesDefs')[fieldName] || !this.getAcl().check(this.model.name, 'edit')) {
                return;
            }

            let attributeId = this.model.get('attributesDefs')[fieldName]['attributeId'] || null;
            if (!attributeId) {
                return;
            }

            if (this.options?.params?.disableAttributeRemove){
                return;
            }

            const $cell = this.getCellElement();
            const $inlineActions = this.getInlineActionsContainer();

            $inlineActions.find('.ph-trash-simple').parent().remove();

            const $removeLink = $(`<a href="javascript:" class="remove-attribute-value hidden" title="${this.translate('Delete')}"><i class="ph ph-trash-simple"></i></a>`);

            if ($inlineActions.size()) {
                $inlineActions.prepend($removeLink);
            } else {
                $cell.prepend($removeLink);
            }

            $removeLink.on('click', () => {
                this.confirm({
                    message: this.translate('confirmRemoveAttributeValue'),
                    confirmText: this.translate('Remove')
                }, () => {
                    const data = {
                        entityName: this.model.name,
                        entityId: this.model.get('id'),
                        attributeId: attributeId
                    }

                    $.ajax({
                        url: `Attribute/action/removeAttributeValue`,
                        type: 'POST',
                        data: JSON.stringify(data),
                        contentType: 'application/json',
                        success: () => {
                            this.model.fetch().then(() => {
                                this.notify('Done', 'success');
                            });
                        }
                    });
                });
            });

            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    $removeLink.removeClass('hidden');
                }
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $removeLink.addClass('hidden');
                }
            });
        },

        initElement: function () {
            this.$element = this.$el.find('[name="' + this.name + '"]');
            if (this.mode === 'edit') {
                this.$element.on('change', function () {
                    this.trigger('change');
                }.bind(this));
                this.$element.on('focus', function () {
                    this.trigger('focus', this.name);
                    this.model.trigger('focusField', this.name);
                }.bind(this));
            }

            this.listenTo(this.model, 'change', () => {
                this.model.trigger('changeField', this.name);
            });
        },

        afterRender: function () {
            if (this.mode === 'edit' || this.mode === 'search') {
                this.initElement();
            }
        },

        setup: function () {
            this.defaultUnit = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'defaultUnit']);
            if (this.params.defaultUnit) {
                this.defaultUnit = this.params.defaultUnit;
            }

            this.listenTo(this.model, 'after:save', () => {
                this.afterModelSave();
                this.reRender();
            });
        },

        afterModelSave() {

        },

        setupSearch: function () {
        },

        getAttributeList: function () {
            return this.getFieldManager().getAttributes(this.fieldType, this.name);
        },

        inlineEditSave: function () {
            var data = this.fetch();

            var self = this;
            var model = this.model;
            var prev = this.initialAttributes;

            model.set(data, {silent: true});
            data = model.attributes;

            var attrs = false;
            for (var attr in data) {
                if (_.isEqual(prev[attr], data[attr])) {
                    continue;
                }
                (attrs || (attrs = {}))[attr] = data[attr];
            }

            if (!attrs) {
                this.inlineEditClose();
                return;
            }

            if (this.validate()) {
                this.notify('Not valid', 'error');
                model.set(prev, {silent: true});
                return;
            }

            this.notify('Saving...');
            model.save(attrs, {
                success: function () {
                    self.trigger('after:save');
                    model.trigger('after:save');
                    self.notify('Saved', 'success');
                },
                error: function () {
                    self.notify('Error occured', 'error');
                    model.set(prev, {silent: true});
                    self.render()
                },
                patch: true
            });
            this.inlineEditClose(true);
        },

        removeInlineEditLinks: function () {
            var $cell = this.getCellElement();
            $cell.find('.inline-save-link').remove();
            $cell.find('.inline-cancel-link').remove();
            $cell.find('.inline-edit-link').addClass('hidden');
        },

        addInlineEditLinks: function () {
            const fieldActions = this.getInlineActionsContainer();
            const $cell = this.getCellElement();
            const $saveLink = $(`<a href="javascript:" class="inline-save-link" title="${this.translate('Update')}"><i class="ph ph-check"></i></a>`);
            const $cancelLink = $(`<a href="javascript:" class="inline-cancel-link" title="${this.translate('Cancel')}"><i class="ph ph-x"></i></a>`);

            if (fieldActions.size()) {
                fieldActions.append($saveLink);
                fieldActions.append($cancelLink);
            } else {
                $cell.prepend($saveLink);
                $cell.prepend($cancelLink);
            }

            $cell.find('.inline-edit-link').addClass('hidden');
            $saveLink.click(function () {
                this.inlineEditSave();
            }.bind(this));
            $cancelLink.click(function () {
                this.inlineEditClose();
            }.bind(this));
        },

        inlineEditClose: function (dontReset) {
            this.trigger('inline-edit-off');
            if (this.mode != 'edit') {
                return;
            }

            this.inlineEditModeIsOn = false;
            this.setMode('detail');
            this.once('after:render', function () {
                this.removeInlineEditLinks();
            }, this);

            if (!dontReset) {
                this.model.set(this.initialAttributes);
            }

            this.reRender(true);
        },

        inlineEdit: function () {
            var self = this;

            this.trigger('edit', this);
            this.setMode('edit');

            this.initialAttributes = this.model.getClonedAttributes();

            this.once('after:render', function () {
                this.addInlineEditLinks();
            }, this);

            this.inlineEditModeIsOn = true;
            this.reRender(true);
            this.trigger('inline-edit-on');
        },

        showValidationMessage: function (message, target) {
            var $el;

            target = target || '.main-element';

            if (typeof target === 'string' || target instanceof String) {
                $el = this.$el.find(target);
            } else {
                $el = $(target);
            }

            if (!$el.size() && this.$element) {
                $el = this.$element;
            }
            $el.popover({
                placement: 'bottom',
                container: 'body',
                content: message,
                trigger: 'manual'
            }).popover('show');

            var isDestroyed = false;

            $el.closest('.field').one('mousedown click', function () {
                if (isDestroyed) return;
                $el.popover('destroy');
                isDestroyed = true;
            });

            this.once('render remove', function () {
                if (isDestroyed) return;
                if ($el) {
                    $el.popover('destroy');
                    isDestroyed = true;
                }
            });

            if (this._timeout) {
                clearTimeout(this._timeout);
            }

            this._timeout = setTimeout(function () {
                if (isDestroyed) return;
                $el.popover('destroy');
                isDestroyed = true;
            }, this.VALIDATION_POPOVER_TIMEOUT);
        },

        validate: function () {
            for (var i in this.validations) {
                var method = 'validate' + Espo.Utils.upperCaseFirst(this.validations[i]);
                if (this[method].call(this)) {
                    this.trigger('invalid');
                    return true;
                }
            }
            return false;
        },

        getLabelText: function () {
            return this.options.labelText || this.translate(this.name, 'fields', this.model.name);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.name) === '' || this.model.get(this.name) === null) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        hasRequiredMarker: function () {
            return this.isRequired();
        },

        isInheritedField: function () {
            if (!['detail', 'edit'].includes(this.mode) || !this.model || !this.model.urlRoot || !this.isInheritableField()) {
                return false;
            }

            const inheritedFields = this.model.get('inheritedFields');

            return inheritedFields && Array.isArray(inheritedFields) && inheritedFields.includes(this.name);
        },

        fetchToModel: function () {
            this.model.set(this.fetch(), {silent: true});
        },

        fetch: function () {
            var data = {};
            data[this.name] = this.$element ? this.$element.val() : null;
            return data;
        },

        clearSearch: function () {
            const field = this.$element || this.$el.find('[name="' + this.name + '"]');
            field.val('');
        },

        fetchSearch: function () {
            var value = this.$element.val().toString().trim();
            if (value) {
                var data = {
                    type: 'equals',
                    value: value
                };
                return data;
            }
            return false;
        },

        getListOptionsData(extensibleEnumId) {
            if (!extensibleEnumId) {
                return []
            }
            let key = 'extensible_enum_' + extensibleEnumId;

            if (!Espo[key]) {
                Espo[key] = [];
                this.ajaxGetRequest(`ExtensibleEnum/action/getExtensibleEnumOptions`, {extensibleEnumId: extensibleEnumId}, {async: false}).then(res => {
                    Espo[key] = res;
                });
            }

            return Espo[key];
        },

        getLinkOptions(scope, customOptions = {}) {
            if (!scope) {
                return [];
            }

            let hash = this.simpleHash(JSON.stringify(customOptions.where ?? []))
            let key = 'link_' + scope + hash;
            if (!Espo[key]) {
                Espo[key] = [];
                let options = {
                    offset: 0,
                    maxSize: 100,
                };

                if (customOptions) {
                    options = {...options, ...customOptions}
                }

                this.ajaxGetRequest(scope, options, {async: false}).then(res => {
                    if (res.list) {
                        Espo[key] = res.list;
                    }
                });
            }

            return Espo[key];
        },

        getMeasureUnits(measureId) {
            if (!measureId) {
                return [];
            }

            let key = 'measure_' + measureId;

            if (!Espo[key]) {
                Espo[key] = [];
                this.ajaxGetRequest(`Unit`, {
                    sortBy: "createdAt",
                    asc: true,
                    offset: 0,
                    maxSize: 5000,
                    where: [
                        {
                            type: "equals",
                            attribute: "measureId",
                            value: measureId
                        },
                        {
                            type: 'isTrue',
                            attribute: 'isActive'
                        }
                    ]
                }, {async: false}).then(res => {
                    if (res.list) {
                        Espo[key] = res.list;
                    }
                });
            }

            return Espo[key];
        },

        getMeasureData(measureId) {
            if (!measureId) {
                return {};
            }

            let key = 'measure_data_' + measureId;
            if (!Espo[key]) {
                Espo[key] = {};
                this.ajaxGetRequest(`Measure`, {
                    where: [
                        {
                            type: "equals",
                            attribute: "id",
                            value: measureId
                        }
                    ]
                }, {async: false}).then(res => {
                    if (res.list && res.list[0]) {
                        Espo[key] = res.list[0];
                    }
                });
            }

            return Espo[key];
        },

        getMeasureFormat() {
            const measure = this.getMeasureData(this.measureId);
            if (measure && measure.displayFormat) {
                return measure.displayFormat.slice(6)
            }
            return null
        },

        loadUnitOptions() {
            this.unitList = [''];
            this.unitListTranslates = {'': ''};
            this.unitListSymbols = {'': ''};

            if (this.measureId) {
                let nameField = 'name'
                const lang = this.params.multilangLocale || this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'multilangLocale'])
                if (lang && lang !== 'main') {
                    nameField += Espo.Utils.hyphenToUpperCamelCase(lang.replaceAll('_', '-').toLowerCase())
                }
                this.getMeasureUnits(this.measureId).forEach(unit => {
                    this.unitList.push(unit.id);
                    this.unitListTranslates[unit.id] = unit[nameField] || unit.name;
                    this.unitListSymbols[unit.id] = unit.symbol
                });
            }
        },

        enable() {
            this.$el.find(`[name="${this.name}"]`).prop('disabled', false)
        },

        disable() {
            this.$el.find(`[name="${this.name}"]`).prop('disabled', true)
        },

        createQueryBuilderFilter() {
            return null;
        },

        renderAfterEl(view, el) {
            setTimeout(() => {
                if ($(el).length) {
                    view.render();
                } else {
                    this.renderAfterEl(view, el);
                }
            }, 100);
        },

        filterInput(rule, inputName) {
            const viewKey = inputName + this.type;
            if (!rule || !inputName) {
                return '';
            }
            if(!this.isNotListeningToOperatorChange) {
                this.isNotListeningToOperatorChange = {};
            }

            if(!this.isNotListeningToOperatorChange[inputName]) {
                this.listenTo(this.model, 'afterUpdateRuleOperator', (rule, previous) => {
                    if (rule.$el.find('.rule-value-container > input').attr('name') !== inputName) {
                        return;
                    }
                    rule.rightValue = null;
                    rule.leftValue = null;
                    let view = this.getView(viewKey);
                    if(rule.operator.type !== 'between' && view){
                       this.filterValue = view.model.get('value');
                        rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                    }
                    this.isNotListeningToOperatorChange[inputName] = true;
                })
            }
            this.filterValue = this.defaultFilterValue;

                this.getModelFactory().create(null, model => {
                    setTimeout(() => {
                        let view =  `views/fields/${this.type}`

                        if( ['wysiwyg','markdown', 'text'].includes(this.type)) {
                            view = 'views/fields/varchar';
                        }
                        this.createView(viewKey, view, {
                            name: 'value',
                            el: `#${rule.id} .field-container.${inputName}`,
                            model: model,
                            mode: 'edit',
                            params: {
                                notNull: true
                            }
                        }, view => {
                            view.render();

                            this.listenTo(model, 'change', () => {
                                if(rule.operator.type === 'between') {
                                    if(inputName.endsWith('value_1')){
                                        rule.rightValue = model.get('value')
                                    }else{
                                        rule.leftValue = model.get('value')
                                    }

                                    if(rule.rightValue && rule.leftValue) {
                                        this.filterValue = [rule.leftValue, rule.rightValue];
                                    }
                                }else{
                                    this.filterValue = model.get('value')
                                }
                                rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                            });
                            this.renderAfterEl(view, `#${rule.id} .field-container`);
                        });
                    }, 50);
                    this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                            if(rule.operator.type === 'between' && Array.isArray(rule.value) && rule.value.length === 2) {
                                model.set('value',inputName.endsWith('value_1') ? rule.value[1]: rule.value[0]);
                            }else{
                                model.set('value', rule.value);
                            }
                    });
                });

            return `<div class="field-container ${inputName}"></div><input type="hidden" real-name="${viewKey}" name="${inputName}" />`;
        },

        filterValueGetter(rule) {
            return this.filterValue;
        }
    });
});
