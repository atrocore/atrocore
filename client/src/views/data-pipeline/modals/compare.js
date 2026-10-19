/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/data-pipeline/modals/compare', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'quick-compare',

        template: 'modals/compare',

        className: 'full-page-modal',

        fullHeight: true,

        backdrop: true,

        hasRightSideView: true,

        recordView: 'views/data-pipeline/record/compare',

        setup() {
            Dep.prototype.setup.call(this);

            this.sourceScope = this.options.sourceScope;
            this.sourceId = this.options.sourceId;

            this.header = this.translate('transformationPreview', 'labels', 'DataPipeline');

            this.buttonList = [
                {
                    name: 'close',
                    label: 'Close',
                    onClick: dialog => dialog.close()
                }
            ];

            if (this.options.fixedTarget) {
                this.targetScopes = this.getAcl().check(this.options.targetScope, 'read') ? [this.options.targetScope] : [];
            } else {
                const links = this.getMetadata().get(['entityDefs', this.sourceScope, 'links']) || {};
                this.targetScopes = Object.keys(links)
                    .filter(link => links[link].foreign === 'source' + this.sourceScope
                        && this.getMetadata().get(['entityDefs', this.sourceScope, 'fields', link, 'dataLineage'])
                        && this.getAcl().check(links[link].entity, 'read'))
                    .map(link => links[link].entity);
            }

            this.targetScope = this.targetScopes.includes(this.options.targetScope) ? this.options.targetScope : this.targetScopes[0];

            this.listenTo(this, 'after:render', () => {
                this.$el.find('.modal-body.body').css('overflow-y', 'hidden');
                this.setupRecord();
            });

            if (!this.targetScope) {
                return;
            }

            this.wait(true);

            const sourceIdPromise = this.sourceId
                ? Promise.resolve(this.sourceId)
                : this.ajaxGetRequest(this.sourceScope, { maxSize: 1, sortBy: 'createdAt', asc: false, select: 'id' })
                    .then(response => response?.list?.[0]?.id || null);

            sourceIdPromise.then(sourceId => {
                if (!sourceId) {
                    this.wait(false);
                    return;
                }

                this.loadSourceModel(sourceId).then(sourceModel => {
                    this.sourceModel = sourceModel;
                    this.loadTargetModel(this.targetScope).then(targetModel => {
                        this.targetModel = targetModel;
                        this.wait(false);
                    });
                });
            });
        },

        loadSourceModel(sourceId) {
            return new Promise(resolve => {
                this.getModelFactory().create(this.sourceScope, sourceModel => {
                    sourceModel.id = sourceId;
                    sourceModel.fetch().then(() => resolve(sourceModel));
                });
            });
        },

        loadTargetModel(targetScope) {
            return new Promise(resolve => {
                this.getModelFactory().create(targetScope, targetModel => {
                    const targetId = this.sourceModel.get('target' + Espo.Utils.upperCaseFirst(targetScope) + 'Id');
                    if (!targetId) {
                        resolve(targetModel);
                        return;
                    }

                    targetModel.id = targetId;
                    targetModel.fetch().then(() => resolve(targetModel));
                });
            });
        },

        loadLayoutData(callback) {
            const layoutData = {};
            const models = [this.sourceModel, this.targetModel];
            let count = 0;

            models.forEach(model => {
                this.getHelper().layoutManager.get(model.name, 'selection', null, null, data => {
                    const layout = [
                        {
                            label: '',
                            style: '',
                            rows: []
                        }
                    ];

                    for (const fieldData of data.layout) {
                        layout[0].rows.push([{
                            ...fieldData,
                            fullWidth: true
                        }]);

                        if (fieldData.attributeId) {
                            model.defs.fields[fieldData.name] = fieldData.fieldDefs;
                            model.defs.fields[fieldData.name].disableAttributeRemove = true;
                        }
                    }

                    layoutData[model.name] = { detailLayout: layout, layoutData: data.layout };

                    count++;
                    if (count === models.length) {
                        callback(layoutData);
                    }
                });
            });
        },

        setupRecord() {
            if (!this.targetModel) {
                const message = this.targetScope ? 'noSourceRecords' : 'noDataPipelineConfigured';
                this.$el.find('.modal-record').text(this.translate(message, 'labels', 'DataPipeline'));
                return;
            }

            this.notify('Loading...');

            this.loadLayoutData(layoutData => {
                this.createView('modalRecord', this.recordView, {
                    el: this.options.el + ' .modal-record',
                    sourceModel: this.sourceModel,
                    targetModel: this.targetModel,
                    targetScopes: this.targetScopes,
                    sourceSelectable: !!this.options.sourceSelectable,
                    layoutData: layoutData
                }, view => {
                    this.listenTo(view, 'all-panels-rendered', () => {
                        this.$el.find('.modal-body.body').css('overflow-y', 'auto');
                        this.notify(false);
                    });

                    this.listenTo(view, 'layout-refreshed', () => {
                        this.loadLayoutData(data => {
                            view.layoutData = data;
                            view.reRender();
                        });
                    });

                    this.listenTo(view, 'target-change', targetScope => {
                        this.notify('Loading...');
                        this.targetScope = targetScope;
                        this.loadTargetModel(targetScope).then(targetModel => {
                            this.targetModel = targetModel;
                            this.loadLayoutData(data => view.setTarget(targetModel, data));
                        });
                    });

                    this.listenTo(view, 'source-change', sourceId => {
                        this.notify('Loading...');
                        this.loadSourceModel(sourceId).then(sourceModel => {
                            this.sourceModel = sourceModel;
                            this.loadTargetModel(this.targetScope).then(targetModel => {
                                this.targetModel = targetModel;
                                this.loadLayoutData(data => view.setSource(sourceModel, targetModel, data));
                            });
                        });
                    });

                    view.render(() => this.setupRightSideView(view));
                });
            });
        },

        setupRightSideView(recordView) {
            const rightContainer = document.querySelector('#' + this.dialog.id + ' .modal-dialog .main-content .right-content');
            if (!rightContainer) {
                return;
            }

            this.destroySveltePanel();

            window['SvelteEntityContextPanel' + this.dialog.id] = new Svelte.EntityContextPanel({
                target: rightContainer,
                props: recordView.getSvelteSideViewProps(this)
            });

            this.dialog.$el.on('hidden.bs.modal', () => this.destroySveltePanel());
        },

        destroySveltePanel() {
            const panel = window['SvelteEntityContextPanel' + this.dialog.id];
            if (panel) {
                try {
                    panel.$destroy();
                } catch (e) {
                }
                delete window['SvelteEntityContextPanel' + this.dialog.id];
            }
        }
    });
});
