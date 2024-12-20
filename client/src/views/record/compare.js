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

        events: {
            'click .button-container .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    if (typeof this[method] == 'function') {
                        this[method].call(this, data, e);
                        e.preventDefault();
                    }
                }
            },
            'click .dropdown-menu .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    if (typeof this[method] == 'function') {
                        this[method].call(this, data, e);
                        e.preventDefault();
                    }
                }
            }
        },

        init() {
            Dep.prototype.init.call(this);
            // this.id = this.model.get('id');
            this.collection = this.options.collection;
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;

            if ('distantModelsAttribute' in this.options && this.instanceComparison) {
                this.distantModelsAttribute = this.options.distantModelsAttribute;
            }

            this.scope = this.name = this.options.scope;
            this.links = this.getMetadata().get('entityDefs.' + this.scope + '.links');
            this.nonComparableFields = this.getMetadata().get('scopes.' + this.scope + '.nonComparableFields') ?? [];
            this.hideQuickMenu = this.options.hideQuickMenu;
        },

        setup() {
            this.instances = this.getMetadata().get(['app', 'comparableInstances'])
            this.notify('Loading...')
            this.getModelFactory().create(this.scope, function (model) {
                this.fieldsArr = [];
                let modelOthers = [];
                let modelCurrent = this.model;

                modelOthers = this.getDistantComparisonModels(model);

                let fieldDefs = this.getMetadata().get(['entityDefs', this.scope, 'fields']) || {};

                Object.entries(fieldDefs).forEach(function ([field, fieldDef]) {

                    if (this.nonComparableFields.includes(field)) {
                        return;
                    }


                    const type = fieldDef['type'];

                    if(!this.isValidType(type) || !this.isFieldEnabled(this.model, field)) {
                        return;
                    }

                    let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'read');

                    if (forbiddenFieldList.includes(field)) {
                        return;
                    }


                    let htmlTag = 'code';

                    this.fieldsArr.push({
                        field: field,
                        type: type,
                        label: fieldDef['label'] ?? field,
                        current: field + 'Current',
                        modelCurrent: modelCurrent,
                        modelOthers: modelOthers,
                        others: modelOthers.map((element, index) => {
                            return {other: field + 'Other' + index, index}
                        }),
                        different: !this.areEquals(modelCurrent, modelOthers, field, fieldDef),
                        required: !!fieldDef['required']
                    });

                }, this);

                this.fieldsArr.sort((v1, v2) =>
                    this.translate(v1.field, 'fields', this.scope).localeCompare(this.translate(v2.field, 'fields', this.scope))
                );

                this.afterModelsLoading(modelCurrent, modelOthers);
                this.listenTo(this, 'after:render', () => {
                    this.setupFieldsPanels();
                    if (this.options.hideRelationship !== true) {
                        this.setupRelationshipsPanels()
                    }
                });
            }, this)

        },

        getDistantComparisonModels(model) {
            return this.collection.models.filter(model => model.id !== this.model.id);
        },

        setupFieldsPanels() {
            this.notify('Loading...')
            this.createView('fieldsPanels', this.fieldsPanelsView, {
                scope: this.scope,
                model: this.model,
                fieldsArr: this.fieldsArr,
                instances: this.instances,
                columns: this.buildComparisonTableHeaderColumn(),
                distantModels: this.distantModelsAttribute,
                instanceComparison: this.instanceComparison,
                el: `${this.options.el} .compare-panel[data-name="fieldsPanels"]`
            }, view => {
                view.render();
                this.notify(false);
            })
        },

        setupRelationshipsPanels() {
            this.notify('Loading...')

            this.getHelper().layoutManager.get(this.scope, 'relationships', layout => {
                this.createView('relationshipsPanels', this.relationshipsPanelsView, {
                    scope: this.scope,
                    model: this.model,
                    relationships: layout,
                    distantModels: this.distantModelsAttribute,
                    collection: this.collection,
                    instanceComparison: this.instanceComparison,
                    columns: this.buildComparisonTableHeaderColumn(),
                    el: `${this.options.el} .compare-panel[data-name="relationshipsPanels"]`
                }, view => {
                    this.notify(false)
                    view.render();
                })
            });
        },

        data() {
            let column = this.buildComparisonTableHeaderColumn()
            return {
                buttonList: this.buttonList,
                fieldsArr: this.fieldsArr,
                columns: column,
                columnLength: column.length + 1,
                scope: this.scope,
                id: this.id
            };
        },

        actionReset() {
            this.confirm(this.translate('confirmation', 'messages'), function () {

            }, this);
        },

        areEquals(current, others, field, fieldDef) {
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

        afterRender() {
            this.notify(false)
        },

        afterModelsLoading(modelCurrent, modelOthers) {
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
                label: hasName ? this.translate('Name') : 'ID'
            });
            this.collection.models.forEach(model => columns.push({
                name: model.get('id'),
                label: hasName ? (model.get('name') ?? 'None') : model.get('id'),
                link: true
            }));

            return columns;
        },

        isValidType(type) {
            return type && type !== 'linkMultiple';
        },

        isFieldEnabled(model, name) {
            if(model.getFieldParam(name, 'notStorable') && !model.getFieldParam(name, 'virtualField')) {
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
    });
});