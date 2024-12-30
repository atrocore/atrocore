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

        buttonList: [],

        fieldsArr: [],

        merging: false,

        events: {
            'change input[type="radio"][name="check-all"]': function (e) {
                e.stopPropagation();
                let id = e.currentTarget.value;
                $('input[data-id="' + id + '"]').prop('checked', true);
                this.model.trigger('select-model', id);
            },

            'click button[data-action="cancel"]': function () {
                this.getParentView().close();
            },

            'click button[data-action="merge"]': function () {
                this.notify('Loading...')
                let fieldsPanels = this.getView('fieldsPanels');
                let relationshipsPanels = this.getView('relationshipsPanels');

                if(fieldsPanels.validate() || relationshipsPanels.validate()) {
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
        },

        setup() {
            this.notify('Loading...');
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

                if(this.merging  && fieldDef['unitField']) {
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

            this.listenTo(this, 'after:render', () => {
                this.notify('Loading...');
                this.renderedPanels = [];
                this.setupFieldsPanels();
                this.setupRelationshipsPanels();
            });

        },

        getOtherModelsForComparison(model) {
            return this.collection.models.filter(model => model.id !== this.model.id);
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
                if(view.isRendered()) {
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
                el: `${this.options.el} .compare-panel[data-name="relationshipsPanels"]`
            }, view => {
                view.render();
                if(view.isRendered()) {
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
                    label: this.translate(link, 'links', this.scope),
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
                id: this.id,
                merging: this.merging
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

            if (fieldDef['type'] === 'link') {
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
                name: `<a href="#/${this.scope}/view/${model.id}"> ${hasName ? (model.get('name') ?? 'None') : model.get('id')} </a>`,
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

        isLinkEnabled(model, name) {
            return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
        },

        isComparableLink(link) {
            let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', link]) ?? {};
            let relationScope = relationDefs['entity'];

            let inverseRelationType = this.getMetadata().get(['entityDefs', relationScope, 'links', relationDefs['foreign'], 'type']);

            return inverseRelationType === relationDefs['type'] && relationDefs['type'] === 'hasMany';
        },

        handlePanelRendering(name) {
            if(this.renderedPanels.includes(name)) {
                return;
            }
            this.renderedPanels.push(name);
            if(this.renderedPanels.length === 2) {
                this.notify(false);
               this.handleRadioButtonsDisableState(false);
                $('.button-container button').removeClass('disabled');
            }
        },

        handleRadioButtonsDisableState(state) {
            let self = this;
            $('input[type="radio"]').each(function(){
                let fieldData = self.fieldsArr.find(el => el.field === $(this).attr('name'));
                if(fieldData && fieldData.disabled) {
                    $(this).prop('disabled', true);
                }else{
                    $(this).prop('disabled', state)
                }
            });
        }
    });
});