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

        selectedFilters: null,

        hideButtonPanel: false,

        hidePanelNavigation: false,

        disableModelFetch: true,

        showOverlay: true,

        models: null,

        selectionModel: null,

        layoutData: null,

        inlineEditDisabled: false,

        recordActionView: false,

        events: {
            'change input[type="radio"][name="check-all"]': function (e) {
                e.stopPropagation();
                let id = e.currentTarget.value;
                $('input[data-id="' + id + '"]').prop('checked', true);
                this.model.trigger('select-model', id);
            },

            'click a[data-action="openOverviewFilter"]': function () {
                this.openOverviewFilter();
            },

            'click a.action': function (e) {
                const $el = $(e.currentTarget);
                const name = $el.data('action');
                if (name) {
                    const functionName = 'action' + Espo.Utils.upperCaseFirst(name);
                    if (typeof this[functionName] === 'function') {
                        this[functionName]($(e.currentTarget).data(), e)
                    }
                }
            }
        },

        setup() {
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.nonComparableFields = this.getMetadata().get('scopes.' + this.scope + '.nonComparableFields') ?? [];
            this.merging = this.options.merging || this.merging;
            this.renderedPanels = [];
            this.hideButtonPanel = false;
            this.selectedFilters = this.selectedFilters || this.getStorage().get('compareFilters', this.scope) || {};
            this.selectionModel = this.options.selectionModel || this.selectionModel;
            this.collection = this.options.collection;
            this.models = this.options.models || this.models;
            this.model = this.getModels().length ? this.getModels()[0] : null;
            this.scope = this.name = this.options.scope || this.model?.name;

            if (typeof this.options.inlineEditDisabled === 'boolean') {
                this.inlineEditDisabled = this.options.inlineEditDisabled;
            }

            this.getModels().forEach(model => {
                this.listenTo(model, 'before:save', (attrs) => {
                    $.each(attrs, (name, value) => {
                        if (!model.defs['fields'][name]) {
                            return;
                        }
                        if ((model.get('attributesDefs') || {})[name]) {
                            return;
                        }
                        if (model.defs['fields'][name].attributeId) {
                            if (!attrs['__attributes']) {
                                attrs['__attributes'] = [model.defs['fields'][name].attributeId];
                            } else {
                                attrs['__attributes'].push(model.defs['fields'][name].attributeId);
                            }
                        }
                    });
                });
            });

            this.setupFieldPanels();
            this.wait(true);
            this.prepareFieldsData(() => {
                this.wait(false);
            });

            this.listenTo(this, 'cancel', (dialog) => {
                if (this.merging) {
                    this.cancelMerging();
                    return;
                }
                dialog.close();
            });

            this.listenTo(this, 'merge', (dialog) => {
                this.applyMerge(() => {
                    dialog.close()
                    if (this.options.mergeCallback) {
                        this.options.mergeCallback();
                    }
                });
            });

            this.listenTo(this, 'open-filter', () => {
                this.openOverviewFilter();
            });


        },

        cancelMerging() {
            if (this.merging) {
                let relationshipsPanels = this.getView('relationshipsPanels');
                this.merging = false;
                if (relationshipsPanels) {
                    relationshipsPanels.changeViewMode('detail');
                    relationshipsPanels.merging = false;
                    relationshipsPanels.reRender();
                }

                this.reRender();
                this.renderFieldsPanels();
                this.$el.find('div.compare-records').attr('data-mode', 'compare')
            }
        },

        applyMerge(doneCallback) {
            let relationshipsPanels = this.getView('relationshipsPanels');
            if (!this.merging) {
                this.notify('Loading...')
                this.merging = true;
                if (relationshipsPanels) {
                    relationshipsPanels.merging = true;
                    relationshipsPanels.changeViewMode('edit');
                    relationshipsPanels.reRender();
                }

                this.reRender();
                this.renderFieldsPanels();
                this.listenTo(this, 'after:fields-panel-rendered', () => {
                    this.handleRadioButtonsDisableState(false)
                })

                this.$el.find('div.compare-records').attr('data-mode', 'merge')
                return;
            }

            this.notify('Loading...');

            let attributes = {};
            let relationshipData = {}


            for (const panel of this.fieldPanels) {
                let fieldsPanels = this.getView(panel.name);

                if (fieldsPanels.validate()) {
                    this.notify(this.translate('fillEmptyFieldBeforeMerging', 'messages'), 'error');
                    return;
                }

                attributes = {...attributes, ...fieldsPanels.fetch()};
            }

            $.each(attributes, (name, value) => {
                if (!this.model.defs['fields'][name]) {
                    return;
                }
                if (this.model.defs['fields'][name].attributeId) {
                    if (!attributes['__attributes']) {
                        attributes['__attributes'] = [this.model.defs['fields'][name].attributeId];
                    } else {
                        attributes['__attributes'].push(this.model.defs['fields'][name].attributeId);
                    }
                }
            });

            let buttons = this.getParentView().$el.find('.modal-footer button');

            if (relationshipsPanels) {
                relationshipData = relationshipsPanels.fetch();

                if (relationshipsPanels.validate()) {
                    this.notify(this.translate('fillEmptyFieldBeforeMerging', 'messages'), 'error');
                    return;
                }
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
            }).done((result) => {
                this.notify('Merged', 'success');
                this.trigger('merge-success', result);
                if (doneCallback) {
                    doneCallback(result);
                }
            });
        },

        getCompareUrl() {
            return 'App/action/merge'
        },

        getCompareData(targetId, attributes, relationshipData) {
            return {
                scope: this.scope,
                attributes: {
                    input: attributes,
                    relationshipData: relationshipData
                },
                sourceIds: this.getModels().map(m => m.id),
            }
        },

        getOtherModelsForComparison(model) {
            return this.getModels().filter(model => model.id !== this.model.id);
        },

        setupFieldPanels() {
            this.fieldPanels = [{
                name: 'fieldsOverviews',
                title: this.translate('Fields'),
                hasLayoutEditor: true,
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

        prepareFieldsData(callback) {
            this.fieldsArr = [];
            let modelCurrent = this.model;
            let modelOthers = this.getOtherModelsForComparison(this.model);

            let forbiddenList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'read');

            let processFields = (fields) => {
                for (const field of fields) {
                    if (forbiddenList.includes(field)) {
                        continue;
                    }

                    if (this.nonComparableFields.includes(field)) {
                        continue;
                    }

                    let fieldDef = this.model.defs.fields[field];

                    if (!fieldDef) {
                        continue;
                    }

                    const type = fieldDef['type'];

                    if ((!fieldDef['ignoreTypeForMerge'] && !this.isValidType(type, field))) {
                        continue;
                    }

                    if (!this.isAllowFieldUsingFilter(field, fieldDef, this.areEquals(modelCurrent, modelOthers, field, fieldDef))) {
                        continue;
                    }

                    if (this.merging && fieldDef['unitField']) {
                        continue;
                    }

                    let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'read');

                    if (forbiddenFieldList.includes(field)) {
                        continue;
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
                        sortOrderInAttributeGroup: fieldDef.sortOrderInAttributeGroup ?? 0,
                        inlineEditDisabled: this.inlineEditDisabled
                    });
                }

                this.fieldPanels = this.fieldPanels.filter(panel => this.fieldsArr.filter(panel.filter).length > 0);
            }

            if (this.layoutData) {
                let fields = this.layoutData.layout.map(row => row.name);
                processFields(fields);
                if (callback) {
                    callback();
                }
            } else {
                this.getHelper().layoutManager.get(this.scope, 'selection', null, null, data => {
                    this.layoutData = data;
                    let fields = []
                    for (const fieldData of this.layoutData.layout) {
                        fields.push(fieldData.name);
                        if (fieldData.attributeId) {
                            this.getModels().forEach(model => {
                                model.defs.fields[fieldData.name] = fieldData.attributeDefs;
                                model.defs.fields[fieldData.name].disableAttributeRemove = true;
                            });
                        }
                    }
                    processFields(fields);

                    if (callback) {
                        callback();
                    }
                });
            }

        },

        renderFieldsPanels() {
            this.renderedPanels = this.renderedPanels.filter(panel => !this.fieldPanels.map(f => f.name).includes(panel));
            let count = 0;
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
                            mergeRowLength: this.getModels().length * 2 + 1,
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

                this.notify('Loading...');

                this.createView(panel.name, this.fieldsPanelsView, {
                    panelTitle: panel.title,
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
                    hasLayoutEditor: !!panel.hasLayoutEditor,
                    el: `${this.options.el} tbody[data-name="${panel.name}"]`
                }, view => {
                    this.listenTo(view, 'data:change', fieldDefs => {
                        this.prepareFieldsData();

                        for (let el of this.fieldsArr) {
                            if (fieldDefs.field === el.field) {
                                if (el.different) {
                                    this.$el.find(`tr[data-field="${el.field}"]`).addClass('danger');
                                } else {
                                    this.$el.find(`tr[data-field="${el.field}"]`).removeClass('danger');
                                }
                                break;
                            }
                        }
                    });

                    view.render(() => {
                        count++;
                        if (count === this.fieldPanels.length) {
                            this.trigger('all-fields-panel-rendered')
                        }
                        this.handlePanelRendering(panel.name);
                        this.trigger('after:fields-panel-rendered', panel.name);
                        this.createView('layoutConfiguratorSelection', "views/record/layout-configurator", {
                            scope: this.scope,
                            viewType: 'selection',
                            layoutData: this.layoutData,
                            alignRight: true,
                            el: this.options.el + ` .panel-title .layout-editor-container`,
                        }, (view) => {
                            view.render()
                            view.on("refresh", () => this.trigger('layout-refreshed'));
                        });
                    });

                }, true);
            });
        },

        getDefaultModelId() {
            return this.getModels()[0].id;
        },

        renderRelationshipsPanels() {
            if (this.isComparisonAcrossScopes()) {
                this.handlePanelRendering('relationshipsPanels');
                return;
            }

            this.prepareRelationshipPanels((panelList) => {
                if (panelList.length === 0) {
                    this.handlePanelRendering('relationshipsPanels');
                    return;
                }

                panelList.forEach(panel => {
                    this.$el.find('table').append(`<tbody class="panel-${panel.name}" id="panel-${panel.name}" data-name="${panel.name}"></tbody>`)
                })

                this.renderedPanels = this.renderedPanels.filter(panel => panel !== 'relationshipsPanels');

                this.notify('Loading...');
                this.createView('relationshipsPanels', this.relationshipsPanelsView, {
                    scope: this.scope,
                    model: this.model,
                    relationshipsPanels: panelList,
                    collection: this.collection,
                    models: this.getModels(),
                    distantModels: this.getDistantModels(),
                    instanceComparison: this.instanceComparison,
                    derivativeComparison: this.options.derivativeComparison,
                    columns: this.buildComparisonTableHeaderColumn(),
                    versionModel: this.options.versionModel,
                    merging: this.merging,
                    selectedFilters: this.selectedFilters
                }, view => {

                    this.listenTo(view, 'all-panels-rendered', () => {
                        this.handlePanelRendering('relationshipsPanels');
                        this.trigger('after:relationship-panels-render')
                    });

                    view.render();

                }, true);
            });
        },

        prepareRelationshipPanels(callback) {
            if (this.isComparisonAcrossScopes()) {
                return [];
            }

            let processLinks = (links) => {
                let relationshipsPanels = [];

                for (let link of links) {

                    if (!this.isLinkEnabled(this.model, link) || this.nonComparableFields.includes(link) ||
                        !this.isComparableLink(link)) {
                        continue;
                    }

                    let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', link]) ?? {};
                    let relationScope = relationDefs['entity'];

                    if (!this.getAcl().check(relationScope, 'read')) {
                        continue;
                    }

                    let inverseRelationType = this.getMetadata().get(['entityDefs', relationScope, 'links', relationDefs['foreign'], 'type']);

                    let relationName = relationDefs['relationName'];

                    if (relationName) {
                        relationName = relationName.charAt(0).toUpperCase() + relationName.slice(1);
                        if (!this.getAcl().check(relationName, 'read')) {
                            continue;
                        }
                    }

                    let title = this.translate(link, 'fields', this.scope);

                    let panelData = {
                        label: title,
                        title: title,
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

                return relationshipsPanels;
            };

            if (this.relationLayoutData) {
                let links = this.relationLayoutData.layout.map(row => row.name);
                processLinks(links);
                let list = processLinks(links);
                if (callback) {
                    callback(list);
                }
            } else {
                this.getHelper().layoutManager.get(this.scope, 'selectionRelations', null, null, data => {
                    this.relationLayoutData = data;
                    let links = data.layout.map(row => row.name);
                    let list = processLinks(links);
                    if (callback) {
                        callback(list);
                    }
                });
            }
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
                hideButtonPanel: this.hideButtonPanel,
                showOverlay: this.showOverlay,
                overlayLogo: this.getFavicon(),
                hasRecordAction: !!this.recordActionView
            };
        },

        actionReset() {
            this.confirm(this.translate('confirmation', 'messages'), function () {

            }, this);
        },

        areEquals(current, others, field, fieldDef) {
            current = current.clone();
            others = others.map(o => o.clone());
            let result = false;
            if (fieldDef['type'] === 'linkMultiple') {
                const fieldId = field + 'Ids';

                if (
                    (current.get(fieldId) && current.get(fieldId).length === 0)
                    && others.map(other => (other.get(fieldId) && other.get(fieldId).length === 0)).reduce((prev, curr) => prev && curr)) {
                    return true;
                }

                result = true;
                for (const other of others) {
                    result = result && current.get(fieldId)?.sort()?.toString() === other.get(fieldId)?.sort()?.toString();
                }
                return result
            }

            if (fieldDef['type'] === 'jsonArray' || this.getMetadata().get(['fields', fieldDef['type'], 'fieldDefs', 'type']) === 'jsonArray') {
                current.get(field)?.sort();
                others.forEach(o => o.get(field)?.sort());
            }

            if (['rangeFloat', 'rangeInt'].includes(fieldDef['type'])) {
                let result = this.areEquals(current, others, field + 'From', this.model.defs.fields[field + 'From'])
                    && this.areEquals(current, others, field + 'To', this.model.defs.fields[field + 'To']);

                if (fieldDef['measureId']) {
                    if (current.get(field + 'Unit') === "") {
                        current.set(field + 'Unit', null)
                    }
                    others.forEach(o => {
                        if (o.get(field + 'Unit') === "") {
                            o.set(field + 'Unit', null)
                        }
                    });
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
                name: this.translate('name', 'fields'),
                isFirst: true,
            });

            this.getModels().forEach((model) => {
                let hasName = model.hasField(model.nameField)
                const name = hasName ? (this.getModelTitle(model) ?? 'None') : model.get('id')

                return columns.push({
                    id: model.id,
                    entityType: model.name,
                    selectionItemId: model.get('_selectionItemId'),
                    action: model.id + 'Action',
                    label: this.getModelTitle(model) ?? model.get('id'),
                    name: `<a href="#/${model.name}/view/${model.id}"  title="${name}" target="_blank"> ${name} </a>`,
                });
            });

            return columns;
        },

        isValidType(type, field) {
            if (this.merging && !this.getFieldManager().isMergeable(type)) {
                return false;
            }
            return type && !['composite'].includes(type);
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

        handlePanelRendering: function (name) {
            if (!this.renderedPanels.includes(name)) {
                this.renderedPanels.push(name);
            }

            if (this.renderedPanels.length === this.fieldPanels.length + 1) {
                this.handleRadioButtonsDisableState(false);
                this.trigger('all-panels-rendered');
                this.notify(false);
                this.$el.find('.overlay').addClass('hidden');
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
                    scope = model.name;
                }
                if (scope !== model.name) {
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

            this.prepareRelationshipPanels(panelList => {
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
                    anchorContainer = $(`<div class="anchor-nav-container" style="display: flex;width: 100%;padding-top: 10px;position:sticky;left: 0;z-index: 100;"></div>`)
                    this.getParentView().$el.find('.modal-body').prepend(anchorContainer)
                }

                setTimeout(() => {
                    this.anchorNavigation = new Svelte.AnchorNavigation({
                        target: anchorContainer.get(0),
                        props: {
                            items: panelList,
                            hasLayoutEditor: true,
                            afterOnMount: () => {
                                this.createLayoutConfigurator();
                            },
                            scrollCallback: (name) => {
                                let panel = this.$el.find(`tbody[data-name="${name}"]`);
                                if (panel.size() > 0) {
                                    panel = panel.get(0);
                                    let content = this.getParentView().$el.find('.modal-body').get(0);
                                    const panelOffset = panel.getBoundingClientRect().top + content.scrollTop - content.getBoundingClientRect().top;
                                    content.scrollTo({
                                        top: panelOffset - 42,
                                        behavior: "smooth"
                                    });
                                }
                            }
                        }
                    });
                }, 100)
            })
        },

        createLayoutConfigurator(selector = null) {
            this.createView('layoutConfigurator', "views/record/layout-configurator", {
                scope: this.scope,
                viewType: 'selectionRelations',
                layoutData: this.layoutData || {},
                el: selector || '.anchor-nav-container .panel-navigation .layout-editor-container',
            }, (view) => {
                view.render();
                view.on("refresh", () => this.trigger('layout-refreshed'));
            })
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
                        this.notify(false)
                    }
                });

                view.render()
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
            if (!filterButton.length && this.getParentView()) {
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

        isPanelsLoading() {
            return this.renderedPanels.length < this.fieldPanels.length + 1;
        }
    });
});