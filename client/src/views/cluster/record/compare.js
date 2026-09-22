/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/cluster/record/compare', ['views/selection/record/detail/compare', 'views/fields/colored-enum'], function (Dep, ColoredEnum) {
    return Dep.extend({

        itemScope: 'ClusterItem',

        relationName: 'clusterItems',

        showCompareHeaderCheckbox: true,

        isComparisonAcrossScopes() {
            return false;
        },

        consolidationPreviewActive: false,

        consolidationPreviewModel: null,

        modelReplacedByPreview: null,

        PREVIEW_COLUMN_ID: '__consolidationPreview__',

        hasGoldenRecord() {
            return !!this.selectionModel && !!this.selectionModel.get('goldenRecordId');
        },

        getSvelteSideViewProps(parentView) {
            const props = Dep.prototype.getSvelteSideViewProps.call(this, parentView);

            // the panel owns the requests; this view only supplies input and renders the outcome
            props.showConsolidation = !this.merging
                && !!this.selectionModel
                && this.selectionModel.get('state') !== 'invalid';
            props.consolidationClusterId = this.selectionId;
            props.consolidationMasterEntity = this.selectionModel ? (this.selectionModel.get('masterEntity') || '') : '';
            props.consolidationPreviewActive = this.consolidationPreviewActive;
            props.consolidationScriptChanged = false;
            props.loadConsolidationEditor = (element, consolidation) => this.mountConsolidationEditor(parentView, element, consolidation);
            props.getConsolidationScript = () => this.getConsolidationScript();
            props.onConsolidationPreviewLoaded = masterRecord => this.showConsolidationPreview(masterRecord);
            props.onConsolidationPreviewDiscarded = () => this.hideConsolidationPreview();
            props.onResetConsolidationScript = () => this.resetConsolidationScript();
            props.onConsolidationScriptSaved = script => this.onConsolidationScriptSaved(script);

            return props;
        },

        mountConsolidationEditor(parentView, element, consolidation) {
            this.getModelFactory().create('Consolidation', model => {
                model.set(consolidation);
                this.consolidationModel = model;
                this.consolidationStoredScript = model.get('consolidationScript') || '';

                parentView.createView('consolidationScriptEditor', 'views/record/right-side-view-panel', {
                    el: '#' + element.id,
                    scope: 'Consolidation',
                    mode: 'edit',
                    model: model,
                    buttonsDisabled: true,
                    sideDisabled: true,
                    bottomDisabled: true,
                    detailLayout: [
                        {
                            rows: [
                                [{ name: 'consolidationScript', fullWidth: true }]
                            ]
                        }
                    ]
                }, view => {
                    this.consolidationEditorView = view;

                    this.listenToOnce(view, 'after:render', () => {
                        const fieldView = view.getFieldView('consolidationScript');

                        // the model is only written on fetch(), so typing is tracked through the editor's own event
                        if (fieldView) {
                            this.listenTo(fieldView, 'script:change', script => {
                                this.syncConsolidationSidebar({
                                    consolidationScriptChanged: script !== this.consolidationStoredScript
                                });
                            });
                        }
                    });

                    view.render();
                });
            });
        },

        getConsolidationScript() {
            if (!this.consolidationEditorView) {
                return null;
            }

            const fetched = this.consolidationEditorView.fetch() || {};

            return 'consolidationScript' in fetched
                ? (fetched.consolidationScript || '')
                : (this.consolidationModel.get('consolidationScript') || '');
        },

        buildPreviewModel(sourceModel, response, id) {
            const preview = sourceModel.clone();
            preview.defs = sourceModel.defs;
            preview.name = sourceModel.name;
            preview.urlRoot = sourceModel.urlRoot;
            preview.item = sourceModel.item;
            preview.set(response);
            preview.set('id', id);

            // the preview column shows values that are not stored yet, so it must never be editable
            const getFieldParam = preview.getFieldParam.bind(preview);
            preview.getFieldParam = (field, param) => param === 'readOnly' ? true : getFieldParam(field, param);

            return preview;
        },

        reRenderWithPreviewColumn() {
            // render() rebuilds the header from getModels(), but the cells come from fieldsArr,
            // which only prepareFieldsData() refreshes - without it the new column renders empty
            this.prepareFieldsData(() => {
                // reRender() takes a force flag, not a callback, so the restore has to hang off the event
                this.listenToOnce(this, 'after:render', () => {
                    (this.checkedIds || []).forEach(id => {
                        this.$el.find(`input.compare-header-checkbox[data-id="${id}"]`).prop('checked', true);
                    });
                    this.markConsolidationPreviewColumn();
                });

                this.reRender();
            });
        },

        showConsolidationPreview(masterRecord) {
            const source = this.hasGoldenRecord()
                ? (this.modelReplacedByPreview
                    || (Dep.prototype.getModels.call(this) || []).find(m => m.id === this.selectionModel.get('goldenRecordId')))
                : null;

            const build = sourceModel => {
                const wasActive = this.consolidationPreviewActive;

                this.consolidationPreviewModel = this.buildPreviewModel(
                    sourceModel,
                    masterRecord,
                    this.hasGoldenRecord() ? sourceModel.id : this.PREVIEW_COLUMN_ID
                );
                this.consolidationPreviewActive = true;

                if (this.model && this.model !== this.consolidationPreviewModel) {
                    if (!this.modelReplacedByPreview) {
                        this.modelReplacedByPreview = this.model;
                    }
                    this.model = this.consolidationPreviewModel;
                }

                if (!this.hasGoldenRecord() && !wasActive) {
                    this.reRenderWithPreviewColumn();
                } else {
                    this.reRenderFieldsPanels();
                    this.markConsolidationPreviewColumn();
                }

                this.syncConsolidationSidebar({ consolidationPreviewActive: true });
            };

            if (source) {
                build(source);
                return;
            }

            this.getModelFactory().create(this.selectionModel.get('masterEntity'), blankModel => build(blankModel));
        },

        hideConsolidationPreview() {
            if (!this.consolidationPreviewActive) {
                return;
            }

            const hadVirtualColumn = !this.hasGoldenRecord();

            this.consolidationPreviewActive = false;
            this.consolidationPreviewModel = null;

            if (this.modelReplacedByPreview) {
                this.model = this.modelReplacedByPreview;
                this.modelReplacedByPreview = null;
            }

            if (hadVirtualColumn) {
                this.reRenderWithPreviewColumn();
            } else {
                this.reRenderFieldsPanels();
                this.$el.find('th.consolidation-preview').removeClass('consolidation-preview');
            }

            this.syncConsolidationSidebar({ consolidationPreviewActive: false });
        },

        onConsolidationScriptSaved(script) {
            this.consolidationStoredScript = script;
            this.consolidationModel.set('consolidationScript', script);
            this.notify(this.translate('Saved'), 'success');
            this.syncConsolidationSidebar({ consolidationScriptChanged: false });
        },

        resetConsolidationScript() {
            if (!this.consolidationEditorView || !this.consolidationModel) {
                return;
            }

            if (this.getConsolidationScript() === this.consolidationStoredScript) {
                return;
            }

            this.consolidationModel.set('consolidationScript', this.consolidationStoredScript);

            const fieldView = this.consolidationEditorView.getFieldView('consolidationScript');
            if (fieldView) {
                fieldView.reRender();
            }

            this.syncConsolidationSidebar({ consolidationScriptChanged: false });
        },

        syncConsolidationSidebar(patch) {
            if (window.SvelteEntityContextPanel?.$set) {
                window.SvelteEntityContextPanel.$set(patch);
            }
        },

        markConsolidationPreviewColumn() {
            if (!this.consolidationPreviewModel) {
                return;
            }
            this.$el.find(`th[data-id="${this.consolidationPreviewModel.id}"]`).addClass('consolidation-preview');
        },

        actionRejectItem(e) {
            const id = $(e.currentTarget).data('selection-item-id');

            this.ajaxPostRequest(`ClusterItem/${id}/reject`)
                .then(response => {
                    this.notify('Item rejected', 'success');
                    this.notify(this.translate('Loading...'));

                    const view = this.getParentView();
                    view.reloadModels(() => view.refreshContent());
                })
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.consolidationPreviewActive) {
                this.markConsolidationPreviewColumn();
            }

            (this.getModels() || [])
                .forEach(model => {
                    const meta = model.item?.get('_meta')?.cluster || {};

                    if (meta.confirmed) {
                        this.$el.find(`th[data-id="${model.id}"]`).addClass('confirmed');
                    }

                    if (meta.golden) {
                        this.$el.find(`th[data-id="${model.id}"]`).addClass('golden');
                    }
                });
        },

        getModels() {
            const models = Dep.prototype.getModels.call(this) || [];

            const sorted = models
                .sort((a, b) => {
                    const aMeta = a.item?.get('_meta')?.cluster || {};
                    const bMeta = b.item?.get('_meta')?.cluster || {};

                    if (!!aMeta.confirmed && !!!bMeta.confirmed) return -1;
                    if (!!!aMeta.confirmed && !!bMeta.confirmed) return 1;
                    return 0;
                })
                .sort((a, b) => a.item?.get('_meta')?.cluster?.golden ? -1 : 1);

            if (!this.consolidationPreviewActive || !this.consolidationPreviewModel) {
                return sorted;
            }

            if (this.hasGoldenRecord()) {
                const previewId = this.consolidationPreviewModel.id;
                return sorted.map(model => model.id === previewId ? this.consolidationPreviewModel : model);
            }

            return [this.consolidationPreviewModel, ...sorted];
        },

        getAdditionalHeaderHtml() {
            let html = `<tr class="matched-score-row">`;

            html += '<th>' + this.translate('matchedScore', 'fields', 'ClusterItem') + '</th>'

            for (let model of this.getModels()) {
                if (this.merging) {
                    html += '<th></th>'
                }
                html += '<th>' + (model.item ? this.getMatchedScoreHtml(model) : '') + '</th>'
            }

            return html + '</tr>'
        },

        getMatchedScoreHtml(model) {
            const value = model.item.get('matchedScore');
            const confirmedAutomatically = model.item.get('_meta')?.cluster?.confirmed && model.item.get('confirmedAutomatically');
            const entityName = model.item.get('entityName');

            let backgroundColor = '#CCCCCC';
            let text = ''

            if (value === null) {
                text = this.translate('N/A');
            } else {
                text = value + '%'

                if (value === 100) {
                    backgroundColor = '#CAF2C2';
                } else if (value > 74) {
                    backgroundColor = '#E0FFCC';
                } else if (value > 49) {
                    backgroundColor = '#FFF8B8';
                } else if (value > 24) {
                    backgroundColor = '#FEFFD6';
                } else {
                    backgroundColor = '#FFE7D1';
                }
            }

            let style = {
                'font-weight': 'normal',
                'background-color': backgroundColor,
                'color': ColoredEnum.prototype.getFontColor.call(this, backgroundColor),
                'border': ColoredEnum.prototype.getBorder.call(this, backgroundColor),
                'padding': '2px 5px',
                'font-size': '100%'
            };

            const styleString = Object.entries(style)
                .map(([key, value]) => `${key}: ${value}`)
                .join('; ');

            let statusIconsHtml = '';
            if (confirmedAutomatically) {
                statusIconsHtml += `<i class="ph ph-sparkle autoconfirmed" title="${this.translate('confirmedAutomatically', 'labels', 'ClusterItem')}"></i>`;
            }

            if (entityName) {
                const primaryEntityId = this.getMetadata().get(['scopes', entityName, 'primaryEntityId']);
                if (primaryEntityId && this.getMetadata().get(['scopes', entityName, 'role']) === 'contributor') {
                    statusIconsHtml += `<i class="ph ph-signpost entity-role-icon" title="${this.translate('contributorRecord', 'labels', 'Cluster')}"></i>`;
                } else {
                    statusIconsHtml += `<i class="ph ph-crown entity-role-icon" title="${this.translate('masterRecord', 'labels', 'Cluster')}"></i>`;
                }
            }

            return `<span class="colored-enum label" style="${styleString}">${text}</span>${statusIconsHtml}`;
        },

        buildComparisonTableHeaderColumn() {
            const columns = Dep.prototype.buildComparisonTableHeaderColumn.call(this);

            if (!this.consolidationPreviewActive || this.hasGoldenRecord()) {
                return columns;
            }

            return columns.map(column => column.id === this.PREVIEW_COLUMN_ID
                ? {
                    ...column,
                    isVirtual: true,
                    label: this.translate('consolidationPreview', 'labels', 'Cluster'),
                    name: this.translate('consolidationPreview', 'labels', 'Cluster')
                }
                : column);
        },

        getMergeUrl() {
            return 'Cluster/merge'
        },

        getMergeData(targetId, attributes, relationshipData) {
            let data = Dep.prototype.getMergeData.call(this, targetId, attributes, relationshipData);
            data.clusterId = this.selectionId;
            return data;
        },
    })
})