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

        disableModelFetch: true,

        models: null,

        events: {
            'change input[type="radio"][name="check-all"]': function (e) {
                e.stopPropagation();
                let id = e.currentTarget.value;
                $('input[data-id="' + id + '"]').prop('checked', true);
                this.model.trigger('select-model', id);
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
            this.merging = this.options.merging || this.merging;
            this.renderedPanels = [];
            this.hideButtonPanel = false;
            this.hidePanelNavigation = false;
            this.selectedFilters = this.getStorage().get('compareFilters', this.scope) || {};
        },

        setup() {
            this.selectionId = this.options.selectionId || this.selectionId;

            if (this.selectionId) {
                this.wait(true);
                this.loadModels(this.selectionId).then(models => {
                    this.models = models;
                    this.model = models[0];
                    this.scope = this.model.name;
                    this.setupFieldPanels();
                    this.prepareFieldsData();
                    this.wait(false)
                });
            } else {
                this.setupFieldPanels();
                this.prepareFieldsData();
            }

            this.listenTo(this, 'cancel', (dialog) => {
                let relationshipsPanels = this.getView('relationshipsPanels');
                if (this.merging) {
                    this.merging = false;
                    relationshipsPanels.changeViewMode('detail');
                    this.renderFieldsPanels();
                    relationshipsPanels.merging = false;
                    return;
                }
                dialog.close();
            });

            this.listenTo(this, 'merge', (dialog) => {
                this.applyMerge(() => dialog.close());
            });

            this.listenTo(this, 'open-filter', () => {
                this.openOverviewFilter();
            });

        },

        applyMerge(doneCallback) {
            let relationshipsPanels = this.getView('relationshipsPanels');
            if (!this.merging) {
                this.notify('Loading...')
                this.merging = true;
                this.renderFieldsPanels();
                this.handleRadioButtonsDisableState(false)
                relationshipsPanels.merging = true;
                relationshipsPanels.changeViewMode('edit');
                this.notify(false)
                return;
            }

            this.notify('Loading...');

            let attributes = {};


            for (const panel of this.fieldPanels) {
                let fieldsPanels = this.getView(panel.name);

                if (fieldsPanels.validate()) {
                    this.notify(this.translate('fillEmptyFieldBeforeMerging', 'messages'), 'error');
                    return;
                }

                attributes = {...attributes, ...fieldsPanels.fetch()};
            }


            let buttons = this.getParentView().$el.find('.modal-footer button');

            let relationshipData = relationshipsPanels.fetch();

            if (relationshipsPanels.validate()) {
                this.notify(this.translate('fillEmptyFieldBeforeMerging', 'messages'), 'error');
                return;
            }

            let id = $('input[type="radio"][name="check-all"]:checked').val();
            buttons.addClass('disabled');
            this.handleRadioButtonsDisableState(true);
            $.ajax({
                url: this.getCompareUrl(),
                type: 'POST',
                selectionId: this.selectionModel?.id,
                data: JSON.stringify(this.getCompareData(id, attributes, relationshipData)),
                error: (xhr, status, error) => {
                    this.notify(false);
                    buttons.removeClass('disabled');
                    this.handleRadioButtonsDisableState(false);
                }
            }).done(() => {
                this.notify('Merged', 'success');
                this.trigger('merge-success');
                if (doneCallback) {
                    doneCallback();
                }
            });
        },

        getCompareUrl() {
            return this.scope + '/action/merge'
        },

        getCompareData(targetId, attributes, relationshipData) {
            return {
                attributes: {
                    input: attributes,
                    relationshipData: relationshipData
                },
                targetId: targetId,
                sourceIds: this.getModels().filter(m => m.id !== targetId).map(m => m.id),
            }
        },

        getOtherModelsForComparison(model) {
            return this.getModels().filter(model => model.id !== this.model.id);
        },

        setupFieldPanels() {
            this.fieldPanels = [{
                name: 'fieldsOverviews',
                title: this.translate('Fields'),
                filter: (field) => !field.attributeId
            }];

            this.putAttributesToModel();

            $.each((this.getConfig().get('referenceData')?.AttributePanel || {}), (code, panel) => {
                this.fieldPanels.push({
                    name: panel.id,
                    title: panel.name,
                    sortOrder: panel.sortOrder,
                    isAttributePanel: true,
                    filter: (field) => field.attributeId && field.attributePanelId === panel.id
                })

                this.fieldPanels.sort((a, b) => a.sortOrder < b.sortOrder ? -1 : 1);
            });

        },

        prepareFieldsData() {
            this.fieldsArr = [];
            let modelCurrent = this.model;
            let modelOthers = this.getOtherModelsForComparison(this.model);


            Object.entries(this.model.defs.fields).forEach(function ([field, fieldDef]) {
                if (this.nonComparableFields.includes(field)) {
                    return;
                }

                const type = fieldDef['type'];

                if ((!fieldDef['ignoreTypeForMerge'] && !this.isValidType(type, field)) || !this.isFieldEnabled(this.model, field)) {
                    return;
                }

                if (!this.isAllowFieldUsingFilter(field, fieldDef, this.areEquals(modelCurrent, modelOthers, field, fieldDef))) {
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
                    shouldNotCenter: ['text', 'wysiwyg', 'markdown'].includes(type) && !!modelCurrent.get(field),
                    class: 'current'
                }];

                modelOthers.forEach((element, index) => {
                    return fieldValueRows.push({
                        id: element.id,
                        key: field + 'Other' + index, index,
                        shouldNotCenter: ['text', 'wysiwyg', 'markdown'].includes(type) && !!element.get(field),
                        class: `other${index}`
                    })
                });

                this.fieldsArr.push({
                    field: field,
                    type: type,
                    label: fieldDef.detailViewLabel ?? fieldDef['label'] ?? this.translate(field, 'fields', this.scope),
                    fieldValueRows: fieldValueRows,
                    different: !this.areEquals(modelCurrent, modelOthers, field, fieldDef),
                    required: !!fieldDef['required'],
                    disabled: this.model.getFieldParam(field, 'readOnly') || field === 'id',
                    attributeId: fieldDef['attributeId'],
                    attributePanelId: fieldDef['attributePanelId'],
                    attributeGroup: fieldDef.attributeGroup,
                    sortOrder: fieldDef.sortOrder,
                    sortOrderInAttributeGroup: fieldDef.sortOrderInAttributeGroup ?? 0
                });
            }, this);

            this.fieldsArr.sort((v1, v2) =>
                v1.label.localeCompare(v2.label)
            );
        },

        renderFieldsPanels() {
            this.fieldPanels.forEach((panel, index) => {
                let fieldList = this.fieldsArr.filter(panel.filter);
                fieldList.sort((a, b) => a.sortOrder < b.sortOrder ? -1 : 1);
                let fieldListByGroup = {};
                fieldList.forEach((field) => {
                    let id = field.attributeGroup?.id ?? 'no-group'
                    if (!fieldListByGroup[id]) {
                        fieldListByGroup[id] = {
                            id: id,
                            name: field.attributeGroup?.name,
                            isInGroup: id !== 'no-group',
                            rowLength: this.getModels().length + 1,
                            fieldListInGroup: [],
                            sortOrder: field.attributeGroup?.sortOrder ?? -1,
                        };
                    }
                    fieldListByGroup[id].fieldListInGroup.push(field);
                });

                fieldListByGroup = Object.values(fieldListByGroup).sort((a, b) => a.sortOrder < b.sortOrder ? -1 : 1);
                for (let fieldListGroup of fieldListByGroup) {
                    if (fieldListGroup.isInGroup) {
                        fieldListGroup.fieldListInGroup.sort((a, b) => a.sortOrderInAttributeGroup < b.sortOrderInAttributeGroup ? -1 : 1);
                    }
                }

                this.createView(panel.name, this.fieldsPanelsView, {
                    scope: this.scope,
                    model: this.model,
                    fieldList: fieldListByGroup,
                    instances: this.instances,
                    columns: this.buildComparisonTableHeaderColumn(),
                    instanceComparison: this.instanceComparison,
                    models: this.getModels(),
                    defaultModelId: this.getDefaultModelId(),
                    merging: this.merging,
                    hideCheckAll: index !== 0,
                    el: `${this.options.el} [data-name="${panel.name}"] .list-container`
                }, view => {
                    view.render();
                    if (view.isRendered()) {
                        this.handlePanelRendering(panel.name);
                        this.trigger('after:fields-panel-rendered');
                    }
                    this.listenTo(view, 'all-fields-rendered', () => {
                        this.handlePanelRendering(panel.name);
                        this.trigger('after:fields-panel-rendered');
                    });
                }, true);
            });
        },

        getDefaultModelId() {
            return this.getModels()[0].id;
        },

        renderRelationshipsPanels() {
            if (this.isComparisonAcrossScopes()) {
                return;
            }
            this.notify('Loading...');
            this.createView('relationshipsPanels', this.relationshipsPanelsView, {
                scope: this.scope,
                model: this.model,
                relationshipsPanels: this.getRelationshipPanels(),
                collection: this.collection,
                models: this.getModels(),
                distantModels: this.getDistantModels(),
                instanceComparison: this.instanceComparison,
                columns: this.buildComparisonTableHeaderColumn(),
                versionModel: this.options.versionModel,
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
                    this.trigger('after:relationship-panels-render')
                });

            }, true);
        },

        getRelationshipPanels() {
            if (this.isComparisonAcrossScopes()) {
                return [];
            }

            let relationshipsPanels = [];
            const bottomPanels = this.getMetadata().get(['clientDefs', this.scope, 'bottomPanels', 'detail']) || [];

            for (let link in this.model.defs.links) {

                if (!this.isLinkEnabled(this.model, link) || this.nonComparableFields.includes(link) ||
                    !this.isComparableLink(link)) {
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

                if (relationDefs['isAssociateRelation']) {
                    Object.entries(this.getMetadata().get(['entityDefs', this.scope, 'links'])).forEach(([name, defs]) => {
                        if (defs?.relationName === relationName && link !== name) {
                            panelData.foreign = name;
                        }
                    })
                }

                relationshipsPanels.push(panelData);
            }

            bottomPanels.forEach(bottomPanel => {
                if (bottomPanel.layoutRelationshipsDisabled) {
                    return;
                }

                let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', bottomPanel.link]);
                if (!relationDefs) {
                    return;
                }

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
                fieldPanels: this.fieldPanels,
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

            if (['rangeFloat', 'rangeInt'].includes(fieldDef['type'])) {
                let result = this.areEquals(current, others, field + 'From', this.model.defs.fields[field + 'From'])
                    && this.areEquals(current, others, field + 'To', this.model.defs.fields[field + 'To']);

                if (fieldDef['measureId']) {
                    result = result && this.areEquals(current, others, field + 'Unit', this.model.defs.fields[field + 'Unit']);
                }

                return result;
            }

            if (fieldDef['unitField']) {
                let mainField = fieldDef['mainField'];
                let mainFieldDef = this.model.defs.fields[mainField];
                let unitIdField = mainField + 'Unit'
                let unitFieldDef = this.model.defs.fields[unitIdField];
                let result = this.areEquals(current, others, unitIdField, unitFieldDef);
                if (mainField !== field) {
                    return result && this.areEquals(current, others, mainField, mainFieldDef);
                }

                if (!result) {
                    return false;
                }
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
                    let view = this.getMetadata().get(['clientDefs', data.scope, 'modalViews', 'compare']) || 'views/modals/compare'
                    this.createView('dialog', view, {
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

            columns.push({
                name: this.translate('Name'),
                isFirst: true,
            });

            this.getModels().forEach((model) => {
                let hasName = !!this.getMetadata().get(['entityDefs', model.name, 'fields', 'name', 'type'])
                return columns.push({
                    id: model.id,
                    entityType: model.name,
                    selectionRecordId: model.get('_selectionRecordId'),
                    name: `<a href="#/${model.name}/view/${model.id}" target="_blank"> ${hasName ? (model.get('name') ?? 'None') : model.get('id')} </a>`,
                });
            });

            return columns;
        },

        isValidType(type, field) {
            if (this.merging && !this.getFieldManager().isMergeable(type)) {
                return false;
            }
            return type && !['linkMultiple', 'composite'].includes(type);
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
                    hide = !(fieldValues.every(value => this.isEmptyValue(value)) && equalValueForModels);
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

            if (relationDefs['isAssociateRelation']) {
                return true;
            }

            let relationScope = relationDefs['entity'];

            let inverseRelationType = this.getMetadata().get(['entityDefs', relationScope, 'links', relationDefs['foreign'], 'type']);

            return inverseRelationType === relationDefs['type'] && relationDefs['type'] === 'hasMany';
        },

        isEmptyValue(value) {
            return value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length);
        },

        handlePanelRendering(name) {
            if (this.renderedPanels.includes(name)) {
                this.notify(false)
                return;
            }
            this.renderedPanels.push(name);
            if (this.renderedPanels.length === 2 || this.fieldPanels.map(f => f.name).includes(name)) {
                this.notify(false);
                this.handleRadioButtonsDisableState(false);
                $('button[data-name="merge"]').removeClass('disabled');
                $('button[data-name="merge"]').attr('disabled', false);
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
            return this.models ?? this.collection.models ?? [];
        },

        isComparisonAcrossScopes() {
            let scope = null;
            for (const model of this.getModels()) {
                if (scope === null) {
                    scope = this.model.name;
                }
                if (scope !== this.model.name) {
                    return true;
                }
            }
        },

        getDistantModels() {
            return null;
        },

        renderPanelNavigationView() {
            if (this.hidePanelNavigation) {
                return;
            }

            let panelList = this.getRelationshipPanels().map(m => {
                m.title = m.label;
                return m;
            });

            panelList = this.getPanelWithFields().concat(panelList);


            if (this.anchorNavigation !== null) {
                try {
                    this.anchorNavigation.$destroy();
                } catch (e) {

                }
            }

            let anchorContainer = this.getParentView().$el.find('.anchor-nav-container');
            if (!anchorContainer.length) {
                let id = Math.floor(Math.random() * 10000);
                anchorContainer = $(`<div class="anchor-nav-container" style="display: flex;width: 100%;padding-top: 10px;"></div>`)
                this.getParentView().$el.find('.modal-footer').append(anchorContainer)
            }

            this.getParentView().$el.find('.modal-footer').css('paddingBottom', '0')

            setTimeout(() => {
                this.anchorNavigation = new Svelte.AnchorNavigation({
                    target: anchorContainer.get(0),
                    props: {
                        items: panelList,
                        scrollCallback: (name) => {
                            let panel = this.$el.find(`.panel[data-name="${name}"]`);
                            if (panel.size() > 0) {
                                panel = panel.get(0);
                                let content = this.getParentView().$el.find('.modal-body').get(0);
                                const panelOffset = panel.getBoundingClientRect().top + content.scrollTop - content.getBoundingClientRect().top;
                                content.scrollTo({
                                    top: window.screen.width < 768 ? panelOffset : panelOffset,
                                    behavior: "smooth"
                                });
                            }
                        }
                    }
                });
            }, 100)

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
                if (!Array.isArray(selected) || selected.length === 0) {
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
                        this.reRenderFieldsPanels();
                    }
                });
            });
        },

        reRenderFieldsPanels() {
            let filterButton = $('a[data-action="openOverviewFilter"]');
            if (this.isOverviewFilterApply()) {
                filterButton.css('color', 'white').addClass('btn-danger').removeClass('btn-default')
            } else {
                filterButton.css('color', 'black').addClass('btn-default').removeClass('btn-danger')
            }

            this.prepareFieldsData();
            this.renderFieldsPanels();
            this.toggleFieldPanels();
            this.renderPanelNavigationView();

            if (this.merging) {
                this.handleRadioButtonsDisableState(false)
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let filterButton = $('a[data-action="openOverviewFilter"]');
            if (!filterButton.length) {
                filterButton = $('<a href="javascript:" class="btn btn-default action pull-right" data-action="openOverviewFilter"' +
                    ' data-original-title="Click to filter" style="color: black;">\n' +
                    '                <i class="ph ph-funnel"></i>\n' +
                    '            </a>');
                this.getParentView().$el.find('.modal-footer').append(filterButton);
            } else {
                filterButton.off('click');
            }
            filterButton.on('click', () => this.trigger('open-filter'))

            if (this.isOverviewFilterApply()) {
                filterButton.css('color', 'white').addClass('btn-danger').removeClass('btn-default')
            } else {
                filterButton.css('color', 'black').addClass('btn-default').removeClass('btn-danger')
            }

            this.notify('Loading...');
            this.renderedPanels = [];

            this.renderFieldsPanels();
            this.renderPanelNavigationView();
            this.renderRelationshipsPanels();
            this.toggleFieldPanels();
        },

        getModelsForAttributes() {
            return [...this.getModels(), this.model].filter((model) => {
                return this.getMetadata().get(`scopes.${model.name}.hasAttribute`);
            });
        },

        putAttributesToModel() {
            let hasAttributeValues = false;

            let models = this.getModelsForAttributes();

            if (models.length === 0) {
                return;
            }

            models.forEach(model => {
                if (!this.disableModelFetch) {
                    model.fetch({async: false})
                }
                $.each(model.defs.fields, (name, defs) => {
                    if (defs.attributeId) {
                        delete this.model.defs.fields[name];
                    }
                })
            });
            models.forEach(model => {
                let attributesDefs = model.get('attributesDefs') || {};

                // prepare composited attributes
                $.each(attributesDefs, (name, defs) => {
                    if (defs.type === 'composite') {
                        (defs.childrenIds || []).forEach(attributeId => {
                            $.each(attributesDefs, (name1, defs1) => {
                                if (defs1.attributeId === attributeId) {
                                    attributesDefs[name1]['compositedField'] = true;
                                }
                            });
                        })
                    }
                });

                models.forEach(model => {
                    $.each(attributesDefs, (name, defs) => {
                        hasAttributeValues = true;
                        if (!model.defs['fields'][name]) {
                            model.defs['fields'][name] = defs;
                        }
                    });
                });
            });
            return hasAttributeValues;
        },

        getPanelWithFields() {
            return this.fieldPanels.filter(panel => this.fieldsArr.filter(panel.filter).length > 0)
        },

        toggleFieldPanels() {
            this.fieldPanels.forEach(panel => {
                let view = $(`${this.options.el} [data-name="${panel.name}"]`)
                if (this.fieldsArr.filter(panel.filter).length > 0) {
                    view.parent().show();
                } else {
                    view.parent().hide();
                }
            })
        },

        remove(dontEmpty) {
            if (this.anchorNavigation !== null) {
                try {
                    this.anchorNavigation.$destroy();
                } catch (e) {

                }
            }
            Dep.prototype.remove.call(this, dontEmpty);
        },

        loadModels(selectionId) {
            let models = [];
            return new Promise((initialResolve, reject) => {
                this.ajaxGetRequest(`selection/${selectionId}/selectionRecords?select=name,entityType,entityId,entity&collectionOnly=true&sortBy=id&asc=false`, {async: false})
                    .then(result => {
                        let entityByScope = {};
                        let order = 0;
                        this.trigger('selection-record:loaded', result.list);
                        for (const entityData of result.list) {
                            if (!entityByScope[entityData.entityType]) {
                                entityByScope[entityData.entityType] = [];
                            }
                            entityData.entity._order = order;
                            entityData.entity._selectionRecordId = entityData.id;

                            entityByScope[entityData.entityType].push(entityData.entity);
                            order++
                        }
                        let promises = [];
                        for (const scope in entityByScope) {
                            promises.push(new Promise((resolve) => {
                                this.getModelFactory().create(scope, model => {
                                    for (const data of entityByScope[scope]) {
                                        let currentModel = Espo.utils.cloneDeep(model);
                                        currentModel.set(data);
                                        currentModel._order = data._order;
                                        models.push(currentModel);
                                    }
                                    resolve();
                                })
                            }));
                        }

                        Promise.all(promises)
                            .then(() => {
                                models.sort((a, b) => a._order - b._order);
                                initialResolve(models);
                            });
                    });
            })
        }
    });
});