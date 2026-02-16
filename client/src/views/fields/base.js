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

Espo.define('views/fields/base', ['view', 'conditions-checker'], function (Dep, ConditionsChecker) {

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

        VALIDATION_POPOVER_TIMEOUT: 5000,

        fieldActions: true,

        inlineEditModeIsOn: false,

        defaultFilterValue: null,

        disableConditions: false,

        disabledInlineActions: false,

        fieldActionsDisabled: false,

        initialMode: null,

        translate: function (name, category, scope) {
            if (category === 'fields' && scope === this.model.name && this.model.getFieldParam(name, 'label')) {
                return this.model.getFieldParam(name, 'label');
            }

            return Dep.prototype.translate.call(this, name, category, scope);
        },

        isRequired() {
            if (this.params.required) {
                return true
            }

            return this.isRequiredViaConditions(this.name);
        },

        getCellElement: function () {
            if (this.isListView()) {
                return this.$el;
            }
            return this.$el.parent();
        },

        getStatusIconsContainer: function () {
            return this.getLabelElement().find('.status-icons');
        },

        getLabelTextContainer: function () {
            return this.getLabelElement().find('.label-text');
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

            this.getCellElement()?.attr('data-readonly', true);
        },

        setNotReadOnly: function () {
            if (this.readOnlyLocked) return;
            this.readOnly = false;
            this.getCellElement()?.removeAttr('data-readonly');
        }, /**
         * Get label element. Works only after rendered.
         * {jQuery}
         */
        getLabelElement: function () {
            return this.$el.parent().children('label');
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
            let tooltipText = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'tooltipText']);

            if (this.model.get('attributesDefs') && this.model.get('attributesDefs')[this.name]) {
                tooltipText = this.model.get('attributesDefs')[this.name]?.tooltipText;
            }

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

            this.initialMode = this.mode = this.options.mode || this.mode;

            this.readOnly = this.readOnly || this.name === 'id' || this.params.readOnly || this.model.getFieldParam(this.name, 'readOnly') || this.model.getFieldParam(this.name, 'clientReadOnly');
            this.readOnlyLocked = this.options.readOnlyLocked || this.readOnly;
            this.inlineEditDisabled = this.options.inlineEditDisabled || this.params.inlineEditDisabled || this.model.getFieldParam(this.name, 'inlineEditDisabled') || this.inlineEditDisabled;
            this.inheritanceActionDisabled = this.options.inheritanceActionDisabled || this.params.inheritanceActionDisabled || this.model.getFieldParam(this.name, 'inheritanceActionDisabled') || this.inheritanceActionDisabled;
            this.readOnly = this.readOnlyLocked || this.options.readOnly || false;
            this.fieldActionsDisabled = this.options.fieldActionsDisabled || this.fieldActionsDisabled;
            this.tooltip = this.options.tooltip || this.params.tooltip || this.model.getFieldParam(this.name, 'tooltip') || (this.getMetadata().get(['entityDefs', this.model.urlRoot, 'fields', this.name, 'tooltipLink']));

            if (this.options.readOnlyDisabled) {
                this.readOnly = false;
            } else {
                if (this.params.protected || this.model.getFieldParam(this.name, 'protected')) {
                    this.readOnly = true;
                }

                if (!this.readOnly) {
                    this.readOnly = this.isReadOnlyViaConditions(this.name);
                }
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
                this.initLinkIfAttribute();
                this.toggleRequiredMarker();

                if (this.readOnly) {
                    this.getCellElement().attr('data-readonly', true);
                } else {
                    this.getCellElement().removeAttr('data-readonly');
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

            if (this.fieldActions && !this.fieldActionsDisabled) {
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

            if (this.hasLockedControls()) {
                this.listenTo(this.model, 'sync', () => {
                    this.setLockedControls();
                });

                this.listenTo(this, 'after:render', () => {
                    this.setLockedControls();
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
                    this.model.set(attributes, { ui: true });
                });
            }
        },

        initStatusContainer: function () {
            if (!['detail', 'edit'].includes(this.mode) && !this.isListView()) {
                return;
            }

            if (this.$el.parents('.stream-head-container').size() > 0) {
                return;
            }

            const label = this.getLabelElement();
            if (label.find('.status-icons').size() === 0) {
                label.append('<sup class="status-icons"></sup>');
            }

            let $cell = this.getCellElement();
            if ($cell.children('.inline-actions').size() === 0) {
                $cell.prepend('<div class="pull-right inline-actions"></div>');
            }
        },

        initInlineActions: function () {
            this.listenTo(this, 'after:render', () => {
                this.initStatusContainer();
                this.initRemoveAttributeValue();
                this.initDynamicFieldActions();
                this.initScriptFieldAction();
                if (!this.inlineEditDisabled) {
                    this.initInlineEdit();
                }
            }, this);
        },

        initInheritanceActions: function () {
            if (!this.inheritanceActionDisabled) {
                this.listenTo(this, 'after:render', () => {
                    this.initStatusContainer();
                    this.initClassificationFieldMarker();
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
            return `<i data-name="${this.name}" class="ph ph-link-simple-horizontal info-field-icon inherited" title="${this.translate('inheritedFromParent')}"></i>`;
        },

        getNonInheritedIconEl: function () {
            return this.getCellElement().find(`.info-field-icon.not-inherited[data-name="${this.name}"]`);
        },

        getNonInheritedIconHtml: function () {
            return `<i data-name="${this.name}" class="ph ph-link-simple-horizontal-break info-field-icon not-inherited" title="${this.translate('notInheritedFromParent')}"></i>`;
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

            $.each((this.getMetadata().get(`entityDefs.${scope}.fields`) || {}), (field, fieldDefs) => {
                if (fieldDefs.inheritanceDisabled) {
                    nonInheritedFields.push(field);
                }
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

        initClassificationFieldMarker: function () {
            if (!this.model.get('attributesDefs')) {
                return;
            }

            const name = this.originalName || this.name;
            const defs = this.model.get('attributesDefs')[name] || null;

            if (!defs || !defs.classificationAttributeId) {
                return;
            }

            if (this.getMetadata().get(['scopes', this.model.urlRoot, 'hasAttribute']) && this.getMetadata().get(['scopes', this.model.urlRoot, 'disableAttributeLinking'])) {
                return;
            }

            if (this.getCellElement().find(`.info-field-icon.classification-attribute[data-name="${this.name}"]`).length === 0) {
                this.getStatusIconsContainer().append(`<i data-name="${this.name}" class="ph ph-tree-structure info-field-icon classification-attribute" title="${this.translate('classificationAttribute', 'labels', 'ClassificationAttribute')}"></i>`);
            }
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

            $cell.on('mouseover', function (e) {
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

        initScriptFieldAction: function () {
            if (!this.model.defs.fields[this.name]) {
                return;
            }
            const type = this.model.defs.fields[this.name].type
            if (type !== 'script') {
                return;
            }

            if ((this.getAcl().getScopeForbiddenFieldList(this.model.name, 'edit') || []).includes(this.name)) {
                return;
            }

            const $cell = this.getCellElement();
            const $inlineActions = this.getInlineActionsContainer();

            $inlineActions.find('.ph-magic-wand').parent().remove();

            const $recalculateLink = $(`<a href="javascript:" class="recalcuate-script hidden" title="${this.translate('recalculateScript')}"><i class="ph ph-magic-wand "></i></a>`);

            if ($inlineActions.size()) {
                $inlineActions.prepend($recalculateLink);
            } else {
                $cell.prepend($recalculateLink);
            }

            $recalculateLink.on('click', () => {
                const data = {
                    scope: this.model.name,
                    id: this.model.id,
                    field: this.name
                }
                $.ajax({
                    url: `App/action/recalculateScriptField`,
                    type: 'POST',
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    success: (data) => {
                        this.model.fetch().then(() => {
                            this.notify('Done', 'success');
                        });
                    }
                });
            })

            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.disabled) {
                    return;
                }
                if (this.mode === 'detail') {
                    $recalculateLink.removeClass('hidden');
                }
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $recalculateLink.addClass('hidden');
                }
            });
        },

        initInlineEdit: function () {
            let $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();
            $cell.find('.inline-edit').parent().remove();

            const $editLink = $(`<a href="javascript:" class="inline-edit-link hidden" title="${this.translate('Edit')}"><i class="ph ph-pencil-simple-line inline-edit"></i></a>`);

            if (inlineActions.size()) {
                inlineActions.prepend($editLink);
            } else {
                $cell.prepend($editLink);
            }

            const name = this.originalName || this.name;
            $cell.off(`click.on-${name}`);
            if (['detail', 'list', 'listLink'].includes(this.mode)) {
                let lastClickTime = 0;

                $cell.on(`click.on-${name}`, e => {
                    // check if double-click for ignoring
                    const now = Date.now();
                    if (now - lastClickTime < 300) {
                        return;
                    }
                    lastClickTime = now;

                    const $target = $(e.target);
                    if (
                        !$target.is('i')
                        && !$target.is('button')
                        && !$target.is('a')
                    ) {
                        setTimeout(() => {
                            const selection = window.getSelection();
                            const selectedText = selection ? selection.toString() : '';
                            if (!selectedText) {
                                this.inlineEdit();
                            }
                        }, 200);
                    }
                });
            }

            $editLink.on('click', function () {
                this.inlineEdit();
            }.bind(this));

            $cell.on('mouseenter', function (e) {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }

                if (this.isListView() && !this.isVisibleViaConditions()) {
                    return;
                }

                if (['detail', 'list', 'listLink'].includes(this.mode)) {
                    $editLink.removeClass('hidden');
                }
            }.bind(this)).on('mouseleave', function (e) {
                e.stopPropagation();
                if (['detail', 'list', 'listLink'].includes(this.mode)) {
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
                const forEditModeOnly = !!this.getMetadata().get(['action', 'typesData', action.type || '', 'forEditModeOnly'])
                const $button = $(`<a href="javascript:" data-for-edit-only="${forEditModeOnly}" class="dynamic-action hidden" style="margin-left: 3px" title="${action.label}">${action.html ?? action.label}</a>`);

                if (inlineActions.size()) {
                    inlineActions.prepend($button);
                } else {
                    $cell.prepend($button);
                }

                $button.on('click', () => {
                    recordView.actionDynamicAction({ id: action.data.action_id })
                });
            })


            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }

                $cell.find('.dynamic-action').each((idx, el) => {
                    $el = $(el)
                    if (this.mode === 'edit') {
                        if ($el.data('forEditOnly')) {
                            $el.removeClass('hidden');
                        } else {
                            $el.addClass('hidden');
                        }
                    } else {
                        if ($el.data('forEditOnly')) {
                            $el.addClass('hidden');
                        } else {
                            $el.removeClass('hidden');
                        }
                    }
                });
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $cell.find('.dynamic-action').addClass('hidden');
                }
            });
        },

        initRemoveAttributeValue() {
            const fieldName = this.originalName || this.name;

            if (!this.model.get('attributesDefs') || !fieldName || !this.model.get('attributesDefs')[fieldName] || !this.getAcl().check(this.model.name, 'edit') || !this.getAcl().check(this.model.name, 'deleteAttributeValue')) {
                return;
            }

            if ((this.getAcl().getScopeForbiddenFieldList(this.model.name, 'edit') || []).includes(this.name)) {
                return;
            }

            let attributeId = this.model.get('attributesDefs')[fieldName]['attributeId'] || null;

            if (!attributeId) {
                return;
            }

            if (this.model.defs.fields[this.name] && this.model.defs.fields[this.name].disableAttributeRemove) {
                return;
            }

            if (this.options?.params?.disableAttributeRemove) {
                return;
            }

            if (this.getMetadata().get(['scopes', this.model.urlRoot, 'hasAttribute']) && this.getMetadata().get(['scopes', this.model.urlRoot, 'disableAttributeLinking'])) {
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
                if (this.disabled) {
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

            if (['edit', 'detail'].includes(this.mode) && !this.options.disableToggleVisibility) {
                this.toggleVisibility();
            }
        },

        initListViewInlineEdit() {
            if (!this.isListView()) {
                return;
            }

            if (this.listInlineEditModeEnabled()) {
                this.initStatusContainer();
                if (!this.inlineEditDisabled) {
                    this.initInlineEdit();
                }
            }

            if (!this.listInlineEditModeEnabled()) {
                this.setReadOnly(true);
            }

            const name = this.originalName || this.name;
            if (this.getMetadata().get(['scopes', this.model.urlRoot, 'hasAttribute']) && this.model.get('attributesDefs') && this.model.get('attributesDefs')[name]) {
                if (!this.model.get('attributesDefs')[name]['attributeValueId']) {
                    if (this.getMetadata().get(['scopes', this.model.urlRoot, 'disableAttributeLinking']) || !this.getAcl().check(this.model.name, 'createAttributeValue')) {
                        this.setReadOnly(true);
                    }
                }
            }

            this.toggleVisibility();

        },

        checkConditionGroup(conditions) {
            return new ConditionsChecker(this).checkConditionGroup(conditions);
        },

        toggleVisibility() {
            const conditions = this.getConditions('visible');
            if (conditions) {
                if (this.checkConditionGroup(conditions)) {
                    if (this.isListView()) {
                        this.getCellElement()?.removeAttr('data-visible');
                    } else {
                        this.getCellElement().show();
                    }
                } else {
                    if (this.isListView()) {
                        this.getCellElement()?.attr('data-visible', false);
                    } else {
                        this.getCellElement().hide();
                    }
                }
            }
        },

        isVisibleViaConditions() {
            const conditions = this.getConditions('visible');
            if (conditions) {
                return this.checkConditionGroup(conditions);
            }

            return true;
        },

        toggleRequiredMarker() {
            if (this.hasRequiredMarker()) {
                this.showRequiredSign();
            } else {
                this.hideRequiredSign();
            }
        },

        isRequiredViaConditions() {
            const conditions = this.getConditions('required');
            if (conditions) {
                return this.checkConditionGroup(conditions);
            }

            return false;
        },

        getReadOnlyConditions() {
            return this.getConditions('protected') || this.getConditions('readOnly');
        },

        isReadOnlyViaConditions() {
            const conditions = this.getReadOnlyConditions();
            if (conditions) {
                return this.checkConditionGroup(conditions);
            }

            return false;
        },

        toggleReadOnlyViaConditions() {
            if (this.params.readOnly || this.model.getFieldParam(this.name, 'readOnly')) {
                return;
            }

            const conditions = this.getReadOnlyConditions();
            if (conditions) {
                const readOnly = this.checkConditionGroup(conditions);
                if (this.getParentView()?.getParentView()?.mode === 'edit') {
                    if (readOnly) {
                        this.setMode('detail');
                    } else {
                        this.setMode('edit');
                    }
                } else if (this.mode === 'edit' && readOnly) {
                    this.inlineEditClose()
                }

                if (readOnly !== this.readOnly) {
                    this.readOnly = readOnly;
                    this.reRender();
                }
            }
        },

        getFieldForConditions() {
            return this.originalName || this.name
        },

        getConditions(type) {
            if (this.disableConditions || this.options.disableConditions) {
                return;
            }

            const fieldName = this.getFieldForConditions();

            const defs = this.model.get('attributesDefs')?.[fieldName] ?? this.getMetadata().get(`entityDefs.${this.model.name}.fields.${fieldName}`)

            return defs?.['conditionalProperties']?.[type]?.['conditionGroup'];
        },

        getDisableOptionsRules() {
            const defs = this.model.get('attributesDefs')?.[this.name] ?? this.getMetadata().get(`entityDefs.${this.model.name}.fields.${this.name}`)

            return defs?.['conditionalProperties']?.['disableOptions'];
        },

        getDisableOptionsViaConditions() {
            let res = [];
            (this.getDisableOptionsRules() || []).forEach(rule => {
                if (this.checkConditionGroup(rule.conditionGroup)) {
                    (rule.options || []).forEach(option => {
                        res.push(option);
                    })
                }
            });

            return res;
        },

        setup: function () {
            this.defaultUnit = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'defaultUnit']);
            if (this.params.defaultUnit) {
                this.defaultUnit = this.params.defaultUnit;
            }

            this.disableConditions = this.options.disableConditions || this.disableConditions;

            // @todo hotfix. For some reasons model does not contain correct data.
            this.listenTo(this.model, 'sync', (model, data) => {
                if (data[this.name] && data[this.name] !== model.get(this.name)) {
                    this.model.set(this.name, data[this.name]);
                }
            });

            this.listenTo(this.model, 'after:save', () => {
                this.afterModelSave();
                this.reRender();
            });

            this.listenTo(this.model, 'after:inlineEditSave', () => {
                if (
                    this.getConditions('readOnly')
                    || this.getConditions('visible')
                    || this.getConditions('required')
                    || (this.getDisableOptionsRules() || []).length > 0
                ) {
                    this.reRender();
                }
            });

            this.listenTo(this.model, 'change', () => {
                if (['edit', 'detail'].includes(this.mode) || this.isListView()) {
                    this.reRenderByConditionalProperties();
                }
            });

            if (this.model && this.model.getFieldParam(this.name, 'mainField')) {
                let name = this.name;
                if (this.model.getFieldParam(this.name, 'unitIdField')) {
                    name += 'Id';
                }
                this.listenTo(this.model, 'change:' + name, () => {
                    this.reRender();
                });
                this.listenTo(this, 'change', () => {
                    this.model.trigger('partFieldChange:' + this.model.getFieldParam(this.name, 'mainField'));
                });
            }

            if (this.isListView()) {
                this.listenTo(this, 'after:render', () => {
                    this.initListViewInlineEdit();
                });
            }
        },

        reRenderByConditionalProperties() {
            this.toggleReadOnlyViaConditions();
            if (
                this.getConditions('visible')
                || this.getConditions('required')
            ) {
                this.reRender();
            }
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

            const name = this.originalName || this.name;
            if (this.isListView() && this.model.get('attributesDefs') && this.model.get('attributesDefs')[name]) {
                if (!this.model.get('attributesDefs')[name]['attributeValueId']) {
                    data.__attributes = [this.model.get('attributesDefs')[this.name]['attributeId']]
                }
            }

            var self = this;
            var model = this.model;
            var prev = Espo.Utils.cloneDeep(this.initialAttributes);

            model.set(data, { silent: true });
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
                model.trigger('after:inlineEditClose');
                return;
            }

            if (this.validate()) {
                this.notify(this.translate('Record cannot be saved'), 'error');
                model.set(prev, { silent: true });
                return;
            }

            let _prev = {};
            $.each(attrs, function (field, value) {
                _prev[field] = prev[field];
            });

            attrs['_prev'] = _prev;
            attrs['_silentMode'] = true;

            model.trigger('before:save', attrs);

            let confirmMessage = this.getConfirmMessage(_prev, attrs, model);
            if (confirmMessage) {
                Espo.Ui.confirm(confirmMessage, {
                    confirmText: self.translate('Apply'),
                    cancelText: self.translate('Cancel')
                }, () => {
                    this.inlineEditSaveModel(model, attrs);
                });
            } else {
                this.inlineEditSaveModel(model, attrs);
            }
        },

        getConfirmMessage: function (_prev, attrs, model) {
            if (model._confirmMessage) {
                return model._confirmMessage;
            }

            let confirmMessage = null;
            if (this.model.get('id')) {
                let confirmations = this.getMetadata().get(`clientDefs.${model.urlRoot}.confirm`) || {};
                $.each(confirmations, (field, data) => {
                    if (_prev[field] !== attrs[field]) {
                        let key = null;
                        if (typeof data.values !== 'undefined') {
                            data.values.forEach(value => {
                                if (attrs[field] === value) {
                                    key = data.message;
                                }
                            });
                        } else {
                            key = data;
                        }

                        if (key) {
                            let parts = key.split('.');
                            confirmMessage = this.translate(parts[2], parts[1], parts[0]);
                        }
                    }
                });
            }

            return confirmMessage;
        },

        inlineEditSaveModel(model, attrs) {
            attrs['_skipIsEntityUpdated'] = true;
            this.notify('Saving...');
            this.ajaxPatchRequest(`${model.name}/${this.model.id}`, attrs)
                .success(res => {
                    this.onInlineEditSave(res, attrs, model);
                })
                .error(xhr => {
                    const statusReason = xhr.responseText || '';
                    if (xhr.status === 409) {
                        xhr.errorIsHandled = true;
                        this.notify(false);
                        Espo.Ui.confirm(statusReason, {
                            confirmText: this.translate('Apply'),
                            cancelText: this.translate('Cancel')
                        }, () => {
                            attrs['_prev'] = null;
                            attrs['_silentMode'] = false;
                            this.ajaxPatchRequest(`${model.name}/${this.model.id}`, attrs).success(res => {
                                this.onInlineEditSave(res, attrs, model);
                            }).error(xhr => {
                                this.onInlineEditError(xhr);
                            });
                        })
                    } else {
                        this.onInlineEditError(xhr);
                    }
                });
        },

        onInlineEditSave(res, attrs, model) {
            if (res.inheritedFields !== undefined) {
                attrs.inheritedFields = res.inheritedFields;
            }

            model.set(attrs);
            model._previousAttributes = res;
            model._updatedById = this.getUser().id; // block realtime
            if (res.isBackendModified) {
                model._fetchAfterInlineEditClose = true;
            }

            // this.trigger('after:save'); // ignored because saving needs to be silent
            // model.trigger('after:save'); // ignored because saving needs to be silent

            if (this.hasLockedControls()) {
                const metaValue = res['_meta']?.locked?.[this.getLockedFieldName()];
                if (metaValue !== undefined) {
                    const meta = model.get('_meta') || {};
                    if (!meta.locked) {
                        meta.locked = {};
                    }

                    meta.locked[this.getLockedFieldName()] = metaValue;

                    this.model.set('_meta', meta);
                }

                this.setLockedControls();
            }

            model.trigger('after:inlineEditSave');
            this.trigger('after:inlineEditSave');

            window.dispatchEvent(new Event('record:save'));
            window.dispatchEvent(new Event('record:actions-reload'));

            this.notify('Saved', 'success');
            this.inlineEditClose(true);
        },

        onInlineEditError(xhr) {
            if (xhr.status >= 400 && xhr.status < 500) {
                this.trigger('invalid');
            }
        },

        removeInlineEditLinks: function () {
            var $cell = this.getCellElement();
            $cell.find('.inline-save-link').remove();
            $cell.find('.inline-cancel-link').remove();
            $cell.find('.inline-edit-link').addClass('hidden');
        },

        addInlineEditLinks: function () {
            this.removeInlineEditLinks();
            if (this.isListView()) {
                return;
            }

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
            this.killAfterOutsideClickListener();
            if (this.mode != 'edit') {
                return;
            }

            this.getCellElement().css('width', '');
            this.getCellElement().css('min-width', '');
            this.getCellElement().css('max-width', '');
            $(window).off('keydown.escape' + this.cid);

            this.inlineEditModeIsOn = false;
            this.setMode(this.initialMode);
            this.once('after:render', function () {
                this.removeInlineEditLinks();
                if (this.model._fetchAfterInlineEditClose && $('.inline-cancel-link').length === 0) {
                    delete this.model._fetchAfterInlineEditClose
                    this.model.fetch();
                }
            }, this);

            if (!dontReset) {
                this.model.set(this.initialAttributes);
            }

            this.reRender(true);
        },

        inlineEdit: function () {
            if (this.readOnly) {
                return false;
            }

            if (this.isListView() && !this.isVisibleViaConditions()) {
                return;
            }

            this.trigger('edit', this);
            this.setMode('edit');

            this.initialAttributes = this.model.getClonedAttributes();

            if (this.isListView()) {
                const width = this.getCellElement().get(0).getBoundingClientRect().width;
                this.getCellElement().css('width', width + 'px');
                this.getCellElement().css('min-width', width + 'px');
                this.getCellElement().css('max-width', width + 'px');
            }
            this.once('after:render', function () {
                this.inlineEditFocusing();
                this.addInlineEditLinks();
                this.initSaveAfterOutsideClick();

                $(window).on('keydown.escape' + this.cid, e => {
                    if (e.key === "Escape") {
                        this.inlineEditClose();
                    }
                });
            }, this);

            this.inlineEditModeIsOn = true;
            this.reRender(true);
            this.trigger('inline-edit-on');
        },

        killAfterOutsideClickListener() {
            const name = this.originalName || this.name;
            this.getElementForOutsideClick().off(`click.anywhere-for-${name}`);
        },

        initSaveAfterOutsideClick() {
            this.killAfterOutsideClickListener();
            const name = this.originalName || this.name;
            this.getElementForOutsideClick().on(`click.anywhere-for-${name}`, e => {
                if (this.mode === 'edit') {
                    let selector = '';
                    if (this.isListView()) {
                        selector = `[data-id=${this.model.id}] .cell[data-name=${this.name}]`;

                        if (this.originalName) {
                            selector += `, [data-id=${this.model.id}] .cell[data-name="${this.originalName}"]`;
                        }
                    } else {
                        selector = `.cell[data-name=${this.name}]`;

                        if (this.originalName) {
                            selector += `, .cell[data-name="${this.originalName}"]`;
                        }
                    }

                    const $target = $(e.target);
                    const $cell = $target.parents(selector);

                    if (
                        $cell.size() === 0
                        && !$target.is('i')
                        && !$target.is('button')
                        && !$target.is('a')
                        && !$target.is('select')
                    ) {
                        this.inlineEditSave();
                    }
                }
            });
        },

        getElementForOutsideClick() {
            if (this.getRecordView().type === 'detail') {
                return this.$el.parents('.middle');
            } else {
                return this.getRecordView().$el;
            }
        },

        inlineEditFocusing() {
            const $input = this.$el.find('input').first();

            $input.focus();
            if ($input[0] && $input[0].type === 'text') {
                const val = $input.val();
                $input[0].setSelectionRange(val.length, val.length);
            }
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

            $el[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            $el.popover({
                placement: 'bottom',
                content: message,
                trigger: 'manual'
            }).popover('show');

            $el.data('isDestroyed', false)

            $el.closest('.field').one('mousedown click', () => {
                if ($el.data('isDestroyed')) return;
                $el.popover('destroy');
                $el.data('isDestroyed', true)
            });

            this.once('render remove', () => {
                if ($el) {
                    if ($el.data('isDestroyed')) return;
                    $el.popover('destroy');
                    $el.data('isDestroyed', true)
                }
            });

            if ($el.data('timeout')) {
                clearTimeout($el.data('timeout'));
            }

            $el.data('timeout', setTimeout(() => {
                if ($el.data('isDestroyed')) return;
                $el.popover('destroy');
                $el.data('isDestroyed', true)
            }, this.VALIDATION_POPOVER_TIMEOUT));
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
            this.model.set(this.fetch(), { silent: true });
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
                this.ajaxGetRequest(`ExtensibleEnum/action/getExtensibleEnumOptions`, { extensibleEnumId: extensibleEnumId }, { async: false }).then(res => {
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
                    options = { ...options, ...customOptions }
                }

                this.ajaxGetRequest(scope, options, { async: false }).then(res => {
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

            return this.getMeasureData(measureId)?.units || [];
        },

        getMeasureData(measureId) {
            if (!measureId) {
                return {};
            }

            let key = 'measure_data_' + measureId;
            if (!Espo[key]) {
                Espo[key] = {};
                this.ajaxGetRequest(`Measure/action/measureWithUnits`, { id: measureId }, { async: false }).then(res => {
                    Espo[key] = res;
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
            this.unitListTranslates = { '': '' };
            this.unitListSymbols = { '': '' };

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
            if (!this.isNotListeningToOperatorChange) {
                this.isNotListeningToOperatorChange = {};
            }

            if (!this.isNotListeningToOperatorChange[inputName]) {
                this.listenTo(this.model, 'afterUpdateRuleOperator', (rule, previous) => {
                    if (rule.$el.find('.rule-value-container > input').attr('name') !== inputName) {
                        return;
                    }
                    rule.rightValue = null;
                    rule.leftValue = null;
                    let view = this.getView(viewKey);


                    if (!['is_null', 'is_not_null', 'current_month', 'last_month', 'next_month', 'current_year', 'last_year'].includes(rule.operator.type)) {
                        if (rule.operator.type !== 'between' && view) {
                            this.filterValue = view.model.get('value');
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        }

                        if (['last_x_days', 'next_x_days'].includes(this.previousOperatorType) && !['last_x_days', 'next_x_days'].includes(rule.operator.type)) {
                            createValueField(rule.operator.type)
                        }

                        if (!['last_x_days', 'next_x_days'].includes(this.previousOperatorType) && ['last_x_days', 'next_x_days'].includes(rule.operator.type)) {
                            createValueField(rule.operator.type)
                        }
                    } else {
                        rule.value = this.defaultFilterValue;
                        if (view) {
                            view.model.set('value', this.defaultFilterValue);
                        }
                    }
                    this.previousOperatorType = rule.operator.type;
                    this.isNotListeningToOperatorChange[inputName] = true;
                })
            }
            this.filterValue = this.defaultFilterValue;
            let createValueField = (type) => this.getModelFactory().create(null, model => {
                model.set('value', this.defaultFilterValue);
                setTimeout(() => {
                    this.previousOperatorType = type ?? rule.operator.type;
                    let view = `views/fields/${this.type}`

                    if (['wysiwyg', 'markdown', 'text'].includes(this.type)) {
                        view = 'views/fields/varchar';
                    } else if (this.type === 'autoincrement') {
                        view = 'views/fields/int';
                    }

                    if (['last_x_days', 'next_x_days'].includes(this.previousOperatorType)) {
                        view = 'views/fields/int'
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
                            if (rule.operator.type === 'between') {
                                if (inputName.endsWith('value_1')) {
                                    rule.rightValue = model.get('value')
                                } else {
                                    rule.leftValue = model.get('value')
                                }

                                if (rule.rightValue != null && rule.leftValue != null) {
                                    this.filterValue = [rule.leftValue, rule.rightValue];
                                }
                            } else {
                                this.filterValue = model.get('value')
                            }
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });
                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });
                }, 50);
                this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                    if (rule.operator.type === 'between' && Array.isArray(rule.value) && rule.value.length === 2) {
                        rule.leftValue = rule.value[0];
                        rule.rightValue = rule.value[1];
                        model.set('value', inputName.endsWith('value_1') ? rule.value[1] : rule.value[0]);
                    } else {
                        model.set('value', rule.value);
                    }
                });
            });

            createValueField();

            return `<div class="field-container ${inputName}"></div><input type="hidden" real-name="${viewKey}" name="${inputName}" />`;
        },

        filterValueGetter(rule) {
            return this.filterValue;
        },

        initLinkIfAttribute() {
            let fieldDefs = this.model.defs.fields[this.name] || this.model.defs.fields[this.defs.name ?? ''];

            if (!fieldDefs || !fieldDefs.attributeId || this.getLabelTextContainer().find('a').length) {
                return;
            }

            this.getLabelTextContainer().html(`<a href="#/Attribute/view/${fieldDefs.attributeId}" target="_blank"> ${this.getLabelTextContainer().text()}</a>`)
        },

        setScriptDefaultValue() {
            if (
                this.model.isNew()
                && !this.model.get('_duplicatingEntityId')
                && this.getMetadata().get(`entityDefs.${this.model.name}.fields.${this.name}.defaultValueType`) === 'script'
                && !(this.options.el || '').includes("stream")
            ) {
                this.model.set(this.name, null);
                this.ajaxGetRequest('App/action/defaultValueForScriptType', {
                    entityName: this.model.name,
                    field: this.name
                }).success(res => {
                    if (this.model.get(this.name) === null) {
                        this.model.set(this.name, res.default);
                    }
                });
            }
        },

        isListView() {
            return ['list', 'listLink'].includes(this.initialMode)
        },

        listInlineEditModeEnabled() {
            return this.getRecordView() && this.getRecordView().listInlineEditModeEnabled;
        },

        setLockedControls: function () {
            this.getStatusIconsContainer().find('.value-locked').remove();
            this.getInlineActionsContainer().find('.value-lock').remove();
            this.getCellElement().off('mouseover.value-lock-' + this.name);
            this.getCellElement().off('mouseleave.value-lock-' + this.name);

            if (!this.hasLockedControls() || this.options.hasFieldLocking === false) {
                return;
            }

            if (this.mode === 'detail' && this.isLocked()) {
                this.getStatusIconsContainer().append(`<i class="ph ph-lock value-locked" title="${this.translate('fieldValueLocked', 'tooltips')}"></i>`);
            }

            if (this.readOnly) {
                return;
            }

            if (this.mode === 'detail' || (this.isListView() && this.listInlineEditModeEnabled())) {
                const action = $(`<a href="javascript:" class="value-lock hidden"><i class="ph ${this.isLocked() ? 'ph-lock-open' : 'ph-lock'}" title="${this.translate(this.isLocked() ? 'unlockFieldValue' : 'lockFieldValue', 'labels')}"></i></a>`);

                action.on('click', (e) => {
                    e.preventDefault();

                    this.notify('Saving...');

                    const url = this.model.urlRoot + '/action/' + (this.isLocked() ? 'unlockField' : 'lockField');
                    this.ajaxPostRequest(url, {
                        entityId: this.model.get('id'),
                        field: this.getLockedFieldName(),
                    }).then(() => {
                        Espo.Ui.success('Success');
                        this.model.fetch();
                    });
                });

                this.getInlineActionsContainer().prepend(action);
                this.getCellElement().on('mouseover.value-lock-' + this.name, () => {
                    this.getInlineActionsContainer().find('.value-lock').removeClass('hidden');
                });

                this.getCellElement().on('mouseleave.value-lock-' + this.name, () => {
                    this.getInlineActionsContainer().find('.value-lock').addClass('hidden');
                });
            }
        },

        getLockedFieldName() {
            return this.getMetadata().get(['entityDefs', this.model.urlRoot, 'fields', this.name, 'mainField']) || this.name;
        },

        isLocked() {
            return !!this.model.get('_meta')?.locked?.[this.getLockedFieldName()];
        },

        hasLockedControls: function () {
            return this.getMetadata().get(['scopes', this.model.urlRoot, 'enableFieldValueLock']) &&
                !this.getMetadata().get(['entityDefs', this.model.urlRoot, this.getLockedFieldName(), 'disableFieldValueLock']);
        }
    });
});
