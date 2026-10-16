/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/data-pipeline/record/compare', ['views/selection/record/detail/compare-entities', 'views/record/detail', 'model'], function (Dep, Detail, Model) {

    return Dep.extend({

        sideView: true,

        PREVIEW_COLUMN_ID: '__dataPipelinePreview__',

        previewActive: false,

        setup() {
            this.sourceModel = this.options.sourceModel;
            this.targetScopes = this.options.targetScopes || [];
            this.model = this.sourceModel;
            this.scope = this.sourceModel.name;
            this.applyTarget(this.options.targetModel, this.options.layoutData);

            if (this.options.sourceSelectable) {
                this.sourceRecordModel = new Model();
                this.sourceRecordModel.defs = {
                    fields: { sourceRecord: { type: 'link', entity: this.sourceModel.name, required: true } },
                    links: { sourceRecord: { type: 'belongsTo', entity: this.sourceModel.name } }
                };
            }
        },

        applyTarget(targetModel, layoutData) {
            if (targetModel.isNew()) {
                targetModel.set('id', this.PREVIEW_COLUMN_ID);
            }

            this.targetModel = targetModel;
            this.layoutData = layoutData;
            this.models = [this.sourceModel, this.targetModel];
            this.previewActive = false;
        },

        setSource(sourceModel, targetModel, layoutData) {
            this.sourceModel = sourceModel;
            this.model = sourceModel;
            this.scope = sourceModel.name;
            this.applyTarget(targetModel, layoutData);
            this.reRender();

            this.syncSidebar({
                dataPipelineSourceRecordId: sourceModel.id,
                dataPipelinePreviewActive: false
            });
        },

        setTarget(targetModel, layoutData) {
            this.applyTarget(targetModel, layoutData);
            this.scriptEditorView = null;
            this.dataPipelineModel = null;
            this.reRender();

            this.syncSidebar({
                dataPipelineTargetEntity: this.targetModel.name,
                dataPipelinePreviewActive: false,
                dataPipelineScriptChanged: false
            });
        },

        getColumns() {
            return this.models.map(model => {
                const entityName = this.translate(model.name, 'scopeNames');
                let name = entityName;

                if (model === this.models[0] && this.options.sourceSelectable) {
                    name = `<span class="source-record-select"><span class="field" data-name="sourceRecord"></span></span>`;
                }

                if (model === this.models[1] && this.targetScopes.length > 1) {
                    const options = this.targetScopes
                        .map(scope => `<option value="${scope}"${scope === model.name ? ' selected' : ''}>${this.translate(scope, 'scopeNames')}</option>`)
                        .join('');
                    name = `<span class="target-entity-select"><select data-name="targetEntity">${options}</select></span>`;
                }

                return {
                    id: model.id,
                    entityType: model.name,
                    action: 'noAction',
                    label: entityName,
                    name: name
                };
            });
        },

        data() {
            return Object.assign(Dep.prototype.data.call(this), { noAction: '' });
        },

        getSvelteSideViewProps(parentView) {
            this.sideViewParent = parentView;

            const thisClone = Espo.utils.clone(this);
            thisClone.scope = this.targetModel.name;
            thisClone.model = this.targetModel;
            thisClone.mode = 'detail';
            thisClone.shouldShowInsights = () => false;
            thisClone.canLoadActivities = () => false;

            const props = Detail.prototype.getSvelteSideViewProps.call(thisClone, parentView);

            props.isCollapsed = false;
            props.useStorage = false;
            props.showCluster = false;
            props.showVersions = false;
            props.showDataPipeline = true;
            props.dataPipelineSourceEntity = this.sourceModel.name;
            props.dataPipelineTargetEntity = this.targetModel.name;
            props.dataPipelineSourceRecordId = this.sourceModel.id;
            props.dataPipelinePreviewActive = this.previewActive;
            props.dataPipelineScriptChanged = false;
            props.loadDataPipelineEditor = (element, dataPipeline) => this.mountScriptEditor(parentView, element, dataPipeline);
            props.getDataPipelineScript = () => this.getScript();
            props.onDataPipelinePreviewLoaded = targetRecord => this.showPreview(targetRecord);
            props.onDataPipelinePreviewDiscarded = () => this.hidePreview();
            props.onResetDataPipelineScript = () => this.resetScript();
            props.onDataPipelineScriptSaved = script => this.onScriptSaved(script);

            return props;
        },

        mountScriptEditor(parentView, element, dataPipeline) {
            this.getModelFactory().create('DataPipeline', model => {
                model.set(dataPipeline);
                this.dataPipelineModel = model;
                this.storedScript = model.get('transformationScript') || '';

                parentView.createView('transformationScriptEditor', 'views/record/right-side-view-panel', {
                    el: '#' + element.id,
                    scope: 'DataPipeline',
                    mode: 'edit',
                    model: model,
                    buttonsDisabled: true,
                    sideDisabled: true,
                    bottomDisabled: true,
                    detailLayout: [
                        {
                            rows: [
                                [{ name: 'transformationScript', fullWidth: true }]
                            ]
                        }
                    ]
                }, view => {
                    this.scriptEditorView = view;

                    this.listenToOnce(view, 'after:render', () => {
                        const fieldView = view.getFieldView('transformationScript');

                        if (fieldView) {
                            this.listenTo(fieldView, 'script:change', script => {
                                this.syncSidebar({ dataPipelineScriptChanged: script !== this.storedScript });
                            });
                        }
                    });

                    view.render();
                });
            });
        },

        getScript() {
            if (!this.scriptEditorView) {
                return null;
            }

            const fetched = this.scriptEditorView.fetch() || {};

            return 'transformationScript' in fetched
                ? (fetched.transformationScript || '')
                : (this.dataPipelineModel.get('transformationScript') || '');
        },

        showPreview(targetRecord) {
            const preview = this.targetModel.clone();
            preview.defs = this.targetModel.defs;
            preview.name = this.targetModel.name;
            preview.urlRoot = this.targetModel.urlRoot;
            preview.set(targetRecord);
            preview.set('id', this.targetModel.id);

            const getFieldParam = preview.getFieldParam.bind(preview);
            preview.getFieldParam = (field, param) => param === 'readOnly' ? true : getFieldParam(field, param);

            this.previewActive = true;
            this.models = [this.sourceModel, preview];
            this.reRender();

            this.syncSidebar({ dataPipelinePreviewActive: true });
        },

        hidePreview() {
            if (!this.previewActive) {
                return;
            }

            this.previewActive = false;
            this.models = [this.sourceModel, this.targetModel];
            this.reRender();

            this.syncSidebar({ dataPipelinePreviewActive: false });
        },

        onScriptSaved(script) {
            this.storedScript = script;
            this.dataPipelineModel.set('transformationScript', script);
            this.notify(this.translate('Saved'), 'success');
            this.syncSidebar({ dataPipelineScriptChanged: false });
        },

        resetScript() {
            if (!this.scriptEditorView || !this.dataPipelineModel) {
                return;
            }

            if (this.getScript() === this.storedScript) {
                return;
            }

            this.dataPipelineModel.set('transformationScript', this.storedScript);

            const fieldView = this.scriptEditorView.getFieldView('transformationScript');
            if (fieldView) {
                fieldView.reRender();
            }

            this.syncSidebar({ dataPipelineScriptChanged: false });
        },

        syncSidebar(patch) {
            const panel = window['SvelteEntityContextPanel' + this.sideViewParent?.dialog?.id];
            if (panel?.$set) {
                panel.$set(patch);
            }
        },

        afterRender() {
            let count = 0;
            const onColumnRendered = () => {
                count++;
                if (count === this.models.length) {
                    this.trigger('all-panels-rendered');
                    this.$el.find('.overlay').addClass('hidden');
                }
            };

            this.$el.find('.compare-entities').addClass('data-pipeline-compare');

            this.$el.find('.bottom-layout-bottoms')
                .on('mouseenter', e => $(e.currentTarget).css('opacity', 1))
                .on('mouseleave', e => $(e.currentTarget).css('opacity', 0));

            this.models.forEach(m => {
                const isPlaceholder = m.id === this.PREVIEW_COLUMN_ID;

                if (this.getAcl().check('LayoutProfile', 'read')) {
                    this.createView(m.id + 'layoutConfiguratorSelection', 'views/record/layout-configurator', {
                        scope: m.name,
                        viewType: 'selection',
                        label: this.translate('Fields'),
                        layoutData: this.layoutData[m.name].layoutData,
                        el: this.options.el + ` td[data-id="${m.id}"] .layout-editor-container.selection`,
                    }, view => {
                        view.render();
                        view.on('refresh', () => this.trigger('layout-refreshed'));
                    });

                    this._helper.layoutManager.get(m.name, 'selectionRelations', null, data => {
                        this.createView(m.id + 'layoutConfiguratorSelectionRelation', 'views/record/layout-configurator', {
                            scope: m.name,
                            viewType: 'selectionRelations',
                            label: this.translate('Relations'),
                            layoutData: data,
                            el: this.options.el + ` td[data-id="${m.id}"] .layout-editor-container.relations`,
                            alignRight: true
                        }, view => {
                            view.render();
                            view.on('refresh', () => this.trigger('layout-refreshed'));
                        });
                    });
                }

                if (isPlaceholder && !this.previewActive) {
                    this.clearView(m.id);
                    this.$el.find(`td[data-id="${m.id}"] .record-content`)
                        .html(`<div class="no-target-record">${this.translate('targetRecordNotExists', 'labels', 'DataPipeline')}</div>`);
                    onColumnRendered();
                    return;
                }

                this.createView(m.id, this.detailComparisonView, {
                    el: this.options.el + ` td[data-id="${m.id}"] .record-content`,
                    scope: m.name,
                    mode: 'detail',
                    model: m,
                    detailLayout: this.layoutData[m.name].detailLayout,
                    bottomView: 'views/selection/record/detail-bottom-comparison',
                    bottomDisabled: isPlaceholder
                }, view => {
                    view.render(onColumnRendered);
                });
            });

            if (this.sourceRecordModel) {
                this.sourceRecordModel.set({
                    sourceRecordId: this.sourceModel.id,
                    sourceRecordName: this.getModelTitle(this.sourceModel) ?? this.sourceModel.id
                }, { silent: true });

                this.createView('sourceRecordField', 'views/fields/link', {
                    el: this.options.el + ' .source-record-select .field[data-name="sourceRecord"]',
                    model: this.sourceRecordModel,
                    name: 'sourceRecord',
                    foreignScope: this.sourceModel.name,
                    defs: { name: 'sourceRecord', params: { required: true } },
                    mode: 'edit',
                    inlineEditDisabled: true,
                    createDisabled: true
                }, view => {
                    this.listenTo(view, 'change', () => {
                        const sourceId = view.fetch().sourceRecordId;
                        if (sourceId && sourceId !== this.sourceModel.id) {
                            this.trigger('source-change', sourceId);
                        }
                    });
                    view.render();
                });
            }

            this.$el.find('select[data-name="targetEntity"]').selectize({
                onChange: value => {
                    if (value && value !== this.targetModel.name) {
                        this.trigger('target-change', value);
                    }
                }
            });

            if (this.previewActive) {
                this.$el.find(`th[data-id="${this.targetModel.id}"]`).addClass('data-pipeline-preview');
            }
        }
    });
});
