/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/detail', ['views/detail', 'model'], function (Dep, Model) {

    return Dep.extend({

        selectionViewMode: 'standard',

        availableModes: ['standard', 'compare', 'merge'],

        hidePanelNavigation: true,

        init: function () {
            Dep.prototype.init.call(this);
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('selectionViewMode') && this.availableModes.includes(urlParams.get('selectionViewMode'))) {
                this.selectionViewMode = urlParams.get('selectionViewMode');
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupCustomButtons();

            this.listenTo(this.model, 'sync', () => {
                this.setupCustomButtons()
            })

            this.listenTo(this.model, 'after:change-mode', (mode) => {
                if (mode === 'detail') {
                    this.setupCustomButtons();
                }
            })
        },


        setupCustomButtons() {
            this.addMenuItem('buttons', {
                name: 'merge',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'merge' ? 'primary' : null,
                html: '<i class="ph ph-arrows-merge "></i> ' + this.translate('Merge'),
                disabled: this.comparisonAcrossEntities()

            }, true, false, true);

            this.addMenuItem('buttons', {
                name: 'compare',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'compare' ? 'primary' : null,
                html: '<i class="ph ph-arrows-left-right"></i> ' + this.translate('Compare'),
                disabled: this.comparisonAcrossEntities()
            }, true, false, true);

            this.addMenuItem('buttons', {
                name: 'standard',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'standard' ? 'primary' : null,
                html: '<i class="ph ph-list"></i> ' + this.translate('Standard')
            }, true, false, true);
        },

        actionShowSelectionView: function (data) {
            if (this.selectionViewMode === data.name) {
                return;
            }

            this.selectionViewMode = data.name;

            this.refreshContent();
        },

        refreshContent() {
            if(this.comparisonAcrossEntities()) {
                this.selectionViewMode = 'standard';
            }
            this.reloadStyle(this.selectionViewMode);
            this.clearView('record');
            this.setupRecord();
        },

        reloadStyle(selected) {

            ['compare', 'standard', 'merge'].forEach(name => {
                $(`.action[data-name="${name}"]`).removeClass('primary');
            })

            $(`.action[data-name="${selected}"]`).addClass('primary');

            if(this.comparisonAcrossEntities()) {
                ['compare', 'merge'].forEach(name => {
                    $(`.action[data-name="${name}"]`).addClass('disabled');
                })
            }
        },

        setupRecord: function () {
            const o = {
                model: this.model,
                el: '#main main > .record',
                scope: this.scope
            };
            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);
            if (this.options.params && this.options.params.rootUrl) {
                o.rootUrl = this.options.params.rootUrl;
            }
            if (!this.navigateButtonsDisabled) {
                o.hasNext = this.hasNext;
            }

            this.treeAllowed = !o.isWide && this.isTreeAllowed();

            this.createView('record', this.getRecordViewName(), o, view => {
                view.render();

                this.listenTo(view, 'detailPanelsLoaded', data => {
                    if (!this.panelsList) {
                        this.standardPanelList = data.list;
                    }
                    this.panelsList = data.list;
                    window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                });

                if (this.selectionViewMode === 'standard') {

                    this.panelsList = this.standardPanelList;

                    if (view.isRendered()) {
                        window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                    }

                    this.listenTo(view.model, 'change', () => {
                        window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                    });

                    this.listenTo(view, 'after:render', view => {
                        window.dispatchEvent(new CustomEvent('detail:panels-loaded', {detail: this.getVisiblePanels()}));
                    });
                }

                if (this.selectionViewMode === 'merge') {
                    this.listenTo(view, 'merge-success', () => {
                        this.refreshContent()
                    })
                }

                if (this.isRendered()) {
                    this.setupCustomButtons();
                    window.dispatchEvent(new CustomEvent('record:buttons-update', {
                        detail: Object.assign({
                            headerButtons: this.getMenu()
                        }, view.getRecordButtons())
                    }));
                }

                this.listenToOnce(this, 'after:render', () => {
                    window.dispatchEvent(new CustomEvent('record:buttons-update', {
                        detail: Object.assign({
                            headerButtons: this.getMenu()
                        }, view.getRecordButtons())
                    }));
                })
            });
        },

        comparisonAcrossEntities() {
            if(Array.isArray(this.model.get('entities'))) {
                return this.model.get('entities').length > 1;
            }
            return true;
        },

        getRecordViewName: function () {
            if (this.selectionViewMode === 'compare') {
                return 'views/selection/record/detail/compare';
            }

            if (this.selectionViewMode === 'merge') {
                return 'views/selection/record/detail/merge';
            }

            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        actionAddItem() {
            this.setupCustomButtons();
            let scope = 'SelectionRecord';
            let viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            let attributes = {_entityFrom: _.extend(this.model.attributes, {_entityName: this.model.name})};

            if (this.getMetadata().get(['scopes', scope, 'hasOwner'])) {
                attributes.ownerUserId = this.getUser().id;
                attributes.ownerUserName = this.getUser().get('name');
            }
            if (this.getMetadata().get(['scopes', scope, 'hasAssignedUser'])) {
                attributes.assignedUserId = this.getUser().id;
                attributes.assignedUserName = this.getUser().get('name');
            }

            this.createView('quickCreate', viewName, {
                scope: scope,
                fullFormDisabled: true,
                relate: {
                    model: this.model,
                    link: 'selections',
                    panelName: 'selectionRecords'
                },
                layoutRelatedScope: "Selection.selectionRecords",
                attributes: attributes,
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    if (this.mode !== 'edit') {
                        this.model.trigger('after:relate', 'selections');
                        this.model.fetch().then(() => {
                            this.refreshContent();
                        })
                    }
                });
            });
        },

        afterRender() {
            this.treeAllowed = false
            Dep.prototype.afterRender.call(this);
            let entities = this.model.get('entities') || [];
            window.treePanelComponent = new Svelte.TreePanel({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                props: {
                    scope: this.scope,
                    model: this.model,
                    mode: 'detail',
                    showApplyQuery: false,
                    showApplySortOrder: false,
                    canBuildTree: entities.length > 0,
                    selectedScope: entities.length > 0 ? this.model.get('entities')[0] : null,
                    callbacks: {
                        selectNode: data => {
                            // view.selectNode(data);
                        },
                        treeLoad: (treeScope, treeData) => {
                            // if (view.treeLoad) {
                            //     view.treeLoad(treeScope, treeData);
                            // }
                        },
                        treeReset: () => {
                            // view.treeReset()
                        },
                        treeWidthChanged: (width) => {
                            // view.onTreeResize(width)
                        },

                        onEntitySelectorAvailable: (element) => {
                            let model = new Model();
                            if(entities.length) {
                                model.set('entityId', entities[0]);
                                model.set('entityName', this.translate(entities[0], 'scopeNames'));
                            }
                            this.createView('entitySelect', 'views/fields/link', {
                                el: `${this.options.el} .content-wrapper .entity-selector`,
                                model: model,
                                name: 'entity',
                                foreignScope: 'Entity',
                                mode: 'edit'
                            }, (view) => {
                                view.render();
                                this.listenTo(view, 'change', () =>{
                                    window.treePanelComponent.setSelectedScope(view.$elementName.attr('value'));
                                    window.treePanelComponent.setCanBuildTree(true);
                                    window.treePanelComponent.rebuildTree()

                                })
                            })
                        }
                    },

                }
            });
        }
    });
});

