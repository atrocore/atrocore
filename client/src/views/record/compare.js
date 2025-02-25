/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/compare', 'view', function (Dep) {

    return Dep.extend({
        template: 'record/compare',

        panelDetailNavigation: null,

        fieldsPanelsView: 'views/record/compare/fields-panels',

        relationshipsPanelsView: 'views/record/compare/relationships-panels',

        panelNavigationView: 'views/record/compare/panel-navigation',

        overviewFilterView: 'views/modals/overview-filter',

        buttonList: [],

        fieldsArr: [],

        merging: false,

        selectedFilters: {},

        hideButtonPanel: false,

        hidePanelNavigation: false,

        events: {
            'change input[type="radio"][name="check-all"]': function (e) {
                e.stopPropagation();
                let id = e.currentTarget.value;
                $('input[data-id="' + id + '"]').prop('checked', true);
                this.model.trigger('select-model', id);
            },

            'click button[data-action="cancel"]': function () {
                let relationshipsPanels = this.getView('relationshipsPanels');
                if (this.merging) {
                    this.merging = false;
                    $('[data-action="cancel"]').addClass('hidden');
                    relationshipsPanels.changeViewMode('detail');
                    this.setupFieldsPanels();
                    relationshipsPanels.merging = false;
                    return;
                }
                this.getParentView().close();
            },

            'click button[data-action="merge"]': function () {
                let relationshipsPanels = this.getView('relationshipsPanels');
                if (!this.merging) {
                    this.notify('Loading...')
                    this.merging = true;
                    $('[data-action="cancel"]').removeClass('hidden');
                    this.setupFieldsPanels();
                    this.handleRadioButtonsDisableState(false)
                    relationshipsPanels.merging = true;
                    relationshipsPanels.changeViewMode('edit');
                    this.notify(false)
                    return;
                }
                this.notify('Loading...')
                let fieldsPanels = this.getView('fieldsPanels');

                if (fieldsPanels.validate() || relationshipsPanels.validate()) {
                    this.notify(this.translate('fillEmptyFieldBeforeMerging', 'messages'), 'error');
                    return;
                }

                let buttons = $('.button-container button');
                let attributes = fieldsPanels.fetch();
                let relationshipData = relationshipsPanels.fetch();

                let id = $('input[type="radio"][name="check-all"]:checked').val();
                buttons.addClass('disabled');
                this.handleRadioButtonsDisableState(true);
                $.ajax({
                    url: this.scope + '/action/merge',
                    type: 'POST',
                    data: JSON.stringify({
                        attributes: {
                            input: attributes,
                            relationshipData: relationshipData
                        },
                        targetId: id,
                        sourceIds: this.collection.models.filter(m => m.id !== id).map(m => m.id),
                    }),
                    error: (xhr, status, error) => {
                        this.notify(false);
                        buttons.removeClass('disabled');
                        this.handleRadioButtonsDisableState(false);
                    }
                }).done(() => {
                    this.notify('Merged', 'success');
                    this.trigger('merge-success');
                    this.getParentView().close();
                });

            },

            'click a[data-action="openOverviewFilter"]': function () {
                this.openOverviewFilter();
            }
        },

        init() {
            Dep.prototype.init.call(this);

            this.scope = this.name = this.options.scope;
            this.collection = this.options.collection;

            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.links = this.getMetadata().get('entityDefs.' + this.scope + '.links');
            this.nonComparableFields = this.getMetadata().get('scopes.' + this.scope + '.nonComparableFields') ?? [];
            this.merging = this.options.merging;
            this.renderedPanels = [];
            this.hideButtonPanel = false;
            this.hidePanelNavigation = false;
        },

        setup() {

            this.listenTo(this, 'after:render', () => {
                $('.full-page-modal  .modal-body').css('overflow', 'auto');
                this.selectedFilters = this.getStorage().get('compareFilters', this.scope) || {};
                let filterButton = $('[data-action="openOverviewFilter"]');
                if(this.isOverviewFilterApply()) {
                    filterButton.css('color', 'white');
                    filterButton.addClass('btn-danger')
                    filterButton.removeClass('btn-default')
                }else{
                    filterButton.css('color', 'black');
                    filterButton.addClass('btn-default')
                    filterButton.removeClass('btn-danger')
                }

                this.notify('Loading...');
                this.renderedPanels = [];
                this.setupFieldsData();
                this.setupFieldsPanels();
                this.setupRelationshipsPanels();
                this.createPanelNavigationView();
            });

        },

        getOtherModelsForComparison(model) {
            return this.collection.models.filter(model => model.id !== this.model.id);
        },

        setupFieldsData() {
            this.fieldsArr = [];
            let modelCurrent = this.model;
            let modelOthers = this.getOtherModelsForComparison(this.model);

            let fieldDefs = this.getMetadata().get(['entityDefs', this.scope, 'fields']) || {};

            Object.entries(fieldDefs).forEach(function ([field, fieldDef]) {

                if (this.nonComparableFields.includes(field)) {
                    return;
                }

                const type = fieldDef['type'];

                if (!this.isValidType(type, field) || !this.isFieldEnabled(this.model, field)) {
                    return;
                }

                if(!this.isAllowFieldUsingFilter(field, fieldDef, this.areEquals(modelCurrent, modelOthers, field, fieldDef))) {
                    return;
                }

                if (this.merging && fieldDef['unitField']) {
                    return;
                }

                let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'read');

                if (forbiddenFieldList.includes(field)) {
                    return;
                }

                let fieldValueRows = [{
                    id: modelCurrent.id,
                    key: field + 'Current',
                    shouldNotCenter: ['text', 'wysiwyg', 'markdown'].includes(type) && modelCurrent.get(field),
                    class: 'current'
                }];

                modelOthers.forEach((element, index) => {
                    return fieldValueRows.push({
                        id: element.id,
                        key: field + 'Other' + index, index,
                        shouldNotCenter: ['text', 'wysiwyg', 'markdown'].includes(type) && element.get(field),
                        class: `other${index}`
                    })
                });

                this.fieldsArr.push({
                    field: field,
                    type: type,
                    label: fieldDef['label'] ?? this.translate(field, 'fields', this.scope),
                    fieldValueRows: fieldValueRows,
                    different: !this.areEquals(modelCurrent, modelOthers, field, fieldDef),
                    required: !!fieldDef['required'],
                    disabled: this.model.getFieldParam(field, 'readOnly') || field === 'id'
                });
            }, this);

            this.fieldsArr.sort((v1, v2) =>
                this.translate(v1.field, 'fields', this.scope).localeCompare(this.translate(v2.field, 'fields', this.scope))
            );
        },

        setupFieldsPanels() {
            this.createView('fieldsPanels', this.fieldsPanelsView, {
                scope: this.scope,
                model: this.model,
                fieldList: this.fieldsArr,
                instances: this.instances,
                columns: this.buildComparisonTableHeaderColumn(),
                instanceComparison: this.instanceComparison,
                models: this.collection.models,
                merging: this.merging,
                el: `${this.options.el} [data-panel="fields-overviews"] .list-container`
            }, view => {
                view.render();
                if (view.isRendered()) {
                    this.handlePanelRendering('fieldsPanels');
                }
                this.listenTo(view, 'all-fields-rendered', () => {
                    this.handlePanelRendering('fieldsPanels');
                });
            }, true);
        },

        setupRelationshipsPanels() {
            this.notify('Loading...');
            this.createView('relationshipsPanels', this.relationshipsPanelsView, {
                scope: this.scope,
                model: this.model,
                relationshipsPanels: this.getRelationshipPanels(),
                collection: this.collection,
                instanceComparison: this.instanceComparison,
                columns: this.buildComparisonTableHeaderColumn(),
                merging: this.merging,
                el: `${this.options.el} #${this.getId()} .compare-panel[data-name="relationshipsPanels"]`,
                selectedFilters: this.selectedFilters
            }, view => {
                view.render();
                if (view.isRendered()) {
                    this.handlePanelRendering('relationshipsPanels');
                }
                this.listenTo(view, 'all-panels-rendered', () => {
                    this.handlePanelRendering('relationshipsPanels');
                });

            }, true);
        },

        getRelationshipPanels() {
            let relationshipsPanels = [];
            const bottomPanels = this.getMetadata().get(['clientDefs', this.scope, 'bottomPanels', 'detail']) || [];

            for (let link in this.model.defs.links) {

                if (!this.isLinkEnabled(this.model, link)) {
                    continue;
                }

                if (this.nonComparableFields.includes(link)) {
                    continue;
                }

                if (!this.isComparableLink(link)) {
                    continue;
                }

                let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', link]) ?? {};
                let relationScope = relationDefs['entity'];

                let inverseRelationType = this.getMetadata().get(['entityDefs', relationScope, 'links', relationDefs['foreign'], 'type']);

                let relationName = relationDefs['relationName'];

                if (relationName) {
                    relationName = relationName.charAt(0).toUpperCase() + relationName.slice(1);
                }

                let panelData = {
                    label: this.translate(link, 'fields', this.scope),
                    scope: relationScope,
                    name: link,
                    type: relationDefs['type'],
                    inverseType: inverseRelationType,
                    foreign: relationDefs['foreign'],
                    relationName: relationName,
                    defs: {},
                    link: link
                };

                relationshipsPanels.push(panelData);
            }

            bottomPanels.forEach(bottomPanel => {
                if (bottomPanel.layoutRelationshipsDisabled) {
                    return;
                }

                let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', bottomPanel.link]);

                let relationName = relationDefs['relationName'];

                if (relationName) {
                    relationName = relationName.charAt(0).toUpperCase() + relationName.slice(1);
                }

                relationshipsPanels.push({
                    label: this.translate(bottomPanel.label, 'labels', this.scope),
                    scope: relationDefs['entity'],
                    name: bottomPanel.name,
                    type: relationDefs['type'],
                    foreign: relationDefs['foreign'],
                    relationName: relationName,
                    defs: bottomPanel,
                    link: bottomPanel.link
                });
            });

            relationshipsPanels.sort(function (v1, v2) {
                return v1.label.localeCompare(v2.label);
            });

            return relationshipsPanels;
        },

        data() {
            let column = this.buildComparisonTableHeaderColumn()
            return {
                fieldsArr: this.fieldsArr,
                columns: column,
                columnLength: column.length,
                scope: this.scope,
                id: this.getId(),
                merging: this.merging,
                hideButtonPanel: this.hideButtonPanel
            };
        },

        actionReset() {
            this.confirm(this.translate('confirmation', 'messages'), function () {

            }, this);
        },

        areEquals(current, others, field, fieldDef) {
            let result = false;
            if (fieldDef['type'] === 'linkMultiple') {
                const fieldId = field + 'Ids';
                const fieldName = field + 'Names'

                if (
                    (current.get(fieldId) && current.get(fieldId).length === 0)
                    && others.map(other => (other.get(fieldId) && other.get(fieldId).length === 0)).reduce((prev, curr) => prev && curr)) {
                    return true;
                }

                result = true;
                for (const other of others) {
                    result = result && current.get(fieldId)?.toString() === other.get(fieldId)?.toString()
                        && current.get(fieldName)?.toString() === other.get(fieldName)?.toString();
                }
                return result
            }

            if (fieldDef['unitField']) {
                let mainField = fieldDef['mainField'];
                let mainFieldDef = this.getMetadata().get(['entityDefs', this.scope, 'fields', mainField]);
                let unitIdField = mainField + 'Unit'
                let unitFieldDef = this.getMetadata().get(['entityDefs', this.scope, 'fields', unitIdField]);
                return this.areEquals(current, others, mainField, mainFieldDef) && this.areEquals(current, others, unitIdField, unitFieldDef);
            }

            if (['link', 'file'].includes(fieldDef['type'])) {
                const fieldId = field + 'Id';
                const fieldName = field + 'Name'
                result = true;

                for (const other of others) {
                    result = result && current.get(fieldId) === other.get(fieldId) && current.get(fieldName) === other.get(fieldName);
                }

                return result;
            }

            result = true;
            for (const other of others) {
                result = result && current.get(field)?.toString() === other.get(field)?.toString();
            }
            return result;

        },

        actionDetailsComparison(data) {
            this.notify('Loading...');
            this.getModelFactory().create(data.scope, (model) => {
                model.id = data.id;
                this.listenToOnce(model, 'sync', function () {
                    this.createView('dialog', 'views/modals/compare', {
                        "model": model,
                        "scope": data.scope,
                        "mode": "details",
                    }, function (dialog) {
                        dialog.render();
                        this.notify(false)
                    })
                }, this);
                model.fetch({main: true});
            });
        },

        buildComparisonTableHeaderColumn() {
            let columns = [];
            let hasName = !!this.getMetadata().get(['entityDefs', this.scope, 'fields', 'name', 'type'])

            columns.push({
                name: hasName ? this.translate('Name') : 'ID',
                isFirst: true,
            });

            this.collection.models.forEach(model => columns.push({
                id: model.id,
                name: `<a href="#/${this.scope}/view/${model.id}" target="_blank"> ${hasName ? (model.get('name') ?? 'None') : model.get('id')} </a>`,
            }));

            return columns;
        },

        isValidType(type, field) {
            if (this.merging && !this.getFieldManager().isMergeable(type)) {
                return false;
            }
            return type && type !== 'linkMultiple';
        },

        isFieldEnabled(model, name) {
            const disabledParameters = ['disabled', 'layoutDetailDisabled'];

            for (let param of disabledParameters) {
                if (model.getFieldParam(name, param)) {
                    return false
                }
            }

            return true;
        },

        isAllowFieldUsingFilter(field, fieldDef, equalValueForModels) {

            const fieldFilter = this.selectedFilters['fieldFilter'] || ['allValues'];
            const languageFilter = this.selectedFilters['languageFilter'] || ['allLanguages'];

            let fields = this.getFieldManager().getActualAttributeList(this.model.getFieldType(field), field);
            let fieldValues = fields.map(field => this.model.get(field));
            if (fieldDef['unitField']) {
                let mainField = fieldDef['mainField'];
                let unitIdField = mainField + 'Unit';
                fields = [mainField, unitIdField];
                fieldValues = fields.map(field => this.model.get(field));
            }
            let hide = false;

            if (!fieldFilter.includes('allValues')) {
                // hide filled
                if (!hide && fieldFilter.includes('filled')) {
                    hide = fieldValues.every(value => this.isEmptyValue(value)) && equalValueForModels;
                }

                // hide empty
                if (!hide && fieldFilter.includes('empty')) {
                    hide = !(fieldValues.every(value => this.isEmptyValue(value)) && equalValueForModels) ;
                }

                // hide optional
                if (!hide && fieldFilter.includes('optional')) {
                    hide = this.model.getFieldParam(field, 'required')
                }

                // hide required
                if (!hide && fieldFilter.includes('required')) {
                    hide = !this.model.getFieldParam(field, 'required');
                }
            }

            if (!languageFilter.includes('allLanguages')) {
                // for languages
                if (!hide && this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                    let fieldLanguage = this.model.getFieldParam(field, 'multilangLocale');

                    if (!languageFilter.includes(fieldLanguage ?? 'main')) {
                        hide = true;
                    }

                    if (!hide && this.isUniLingualField(field, fieldLanguage)) {
                        hide = true
                    }

                    if (hide && languageFilter.includes('unilingual') && this.isUniLingualField(field, fieldLanguage)) {
                        hide = false;
                    }

                }
            }

            return !hide;
        },

        isUniLingualField(name, fieldLanguage) {
            return !(this.model.getFieldParam(name, 'isMultilang') || fieldLanguage !== null);
        },

        isLinkEnabled(model, name) {
            return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
        },

        isComparableLink(link) {
            let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', link]) ?? {};
            let relationScope = relationDefs['entity'];

            let inverseRelationType = this.getMetadata().get(['entityDefs', relationScope, 'links', relationDefs['foreign'], 'type']);

            return inverseRelationType === relationDefs['type'] && relationDefs['type'] === 'hasMany';
        },

        isEmptyValue(value) {
            return value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length);
        },

        handlePanelRendering(name) {
            if (this.renderedPanels.includes(name)) {
                return;
            }
            this.renderedPanels.push(name);
            if (this.renderedPanels.length === 2 || name === 'fieldsPanels') {
                this.notify(false);
                this.handleRadioButtonsDisableState(false);
                $('.button-container button').removeClass('disabled');
                $('.button-container a').removeClass('disabled');
            }
        },

        handleRadioButtonsDisableState(state) {
            let self = this;
            $('input[type="radio"]').each(function () {
                let fieldData = self.fieldsArr.find(el => el.field === $(this).attr('name'));
                if (fieldData && fieldData.disabled) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', state)
                }
            });
        },

        getId() {
            return this.simpleHash(JSON.stringify(this.getModels().map(m => m.id)));
        },

        getModels() {
            return this.collection.models ?? [];
        },

        createPanelNavigationView() {
            if(this.hidePanelNavigation) {
                return;
            }
            let panelList = this.getRelationshipPanels().map(m => {
                m.title = m.label;
                return m;
            });

            this.createView('panelDetailNavigation', this.panelNavigationView, {
                panelList: panelList,
                model: this.model,
                el: this.options.el + ' #' + this.getId() + ' .panel-navigation.panel-left',
            }, function (view) {
                view.render();
            });
        },

        getOverviewFiltersList: function () {
            if (this.overviewFilterList) {
                return this.overviewFilterList;
            }
            let result = [
                {
                    name: "fieldFilter",
                    label: this.translate('fieldStatus'),
                    options: ["allValues", "filled", "empty", "optional", "required"],
                    selfExcludedFieldsMap: {
                        filled: 'empty',
                        empty: 'filled',
                        optional: 'required',
                        required: 'optional'
                    },
                    defaultValue: 'allValues'
                }
            ];

            if (this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                let referenceData = this.getConfig().get('referenceData');

                if (referenceData && referenceData['Language']) {
                    let languages = referenceData['Language'] || {},
                        options = ['allLanguages', 'unilingual'],
                        translatedOptions = {};

                    options.forEach(option => {
                        translatedOptions[option] = this.getLanguage().translateOption(option, 'languageFilter', 'Global');
                    });

                    Object.keys(languages || {}).forEach((lang) => {
                        if (languages[lang]['role'] === 'main') {
                            options.push('main');
                            translatedOptions['main'] = languages[lang]['name'];
                        } else {
                            options.push(lang);
                            translatedOptions[lang] = languages[lang]['name'];
                        }
                    });

                    result.push({
                        name: "languageFilter",
                        label: this.translate('language'),
                        options,
                        translatedOptions,
                        defaultValue: 'allLanguages'
                    });
                }
            }

            return this.overviewFilterList = result;
        },

        isOverviewFilterApply() {
            for (const filter of this.getOverviewFiltersList()) {
                let selected = this.selectedFilters[filter.name] ?? [];
                if (!Array.isArray(selected)  || selected.length === 0) {
                    continue;
                }
                if (selected && selected.join('') !== filter.defaultValue) {
                    return true;
                }
            }

            return false;
        },

        openOverviewFilter: function () {
            this.notify('Loading...');
            let currentValues = {};
            let overviewFilterList = this.getOverviewFiltersList();
            overviewFilterList.forEach((filter) => {
                currentValues[filter.name] = this.selectedFilters[filter.name];
            });
            this.createView('compareOverviewFilter', this.overviewFilterView, {
                scope: this.scope,
                model: this.model,
                overviewFilters: overviewFilterList,
                currentValues: currentValues
            }, view => {
                view.render()
                if (view.isRendered()) {
                    this.notify(false)
                }
                this.listenTo(view, 'after:render', () => {
                    this.notify(false)
                });

                this.listenTo(view, 'save', (filterModel) => {
                    let filterChanged = false;
                    this.getOverviewFiltersList().forEach((filter) => {
                        if (filterModel.get(filter.name)) {
                            filterChanged = true;
                            this.selectedFilters[filter.name] = filterModel.get(filter.name);
                        }
                    });

                    if (filterChanged) {
                        this.model.trigger('overview-filters-changed', this.selectedFilters);
                        this.getStorage().set('compareFilters', this.scope, this.selectedFilters)
                        this.reRender();
                    }
                });
            });
        },
    });
});