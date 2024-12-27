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
        },

        init() {
            Dep.prototype.init.call(this);

            this.scope = this.name = this.options.scope;
            this.collection = this.options.collection;

            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.links = this.getMetadata().get('entityDefs.' + this.scope + '.links');
            this.nonComparableFields = this.getMetadata().get('scopes.' + this.scope + '.nonComparableFields') ?? [];
            this.merging = this.options.merging;
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
                    required: !!fieldDef['required']
                });
            }, this);

            this.fieldsArr.sort((v1, v2) =>
                this.translate(v1.field, 'fields', this.scope).localeCompare(this.translate(v2.field, 'fields', this.scope))
            );

            this.listenTo(this, 'after:render', () => {
                this.notify('Loading...');
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
            })
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
                this.listenTo(view, 'after:render', () => this.notify(false))
            })
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
                    defs: {}
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
                    defs: bottomPanel
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
            if (this.merging && model.getFieldParam(name, 'readOnly')) {
                return false;
            }

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
        }
    });
});