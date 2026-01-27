/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare-entities', ['view', 'views/record/detail',   'views/record/panels/relationship'], function (Dep, Detail, Relationship) {

    return Dep.extend({

        template: 'selection/record/compare-entities',

        models: [],

        detailComparisonView: 'views/selection/record/detail/detail-comparison-view',

        showOverlay: true,

        relationName: 'selectionItems',

        itemScope: 'SelectionItem',

        recordActionView: 'views/record/row-actions/relationship',

        events: {
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
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.models = this.options.models || this.models;
            this.model = this.models.length ? this.models[0] : null;
            this.scope = this.options.scope || this.selectionModel?.name;
            this.layoutData = this.options.layoutData;

            this.listenTo(this.selectionModel, 'overview-filters-changed', () => {
                this.models.forEach(model => {
                    model.trigger('overview-filters-changed')
                });
            });

            this.listenToOnce(this, 'after:render', () => {
                this.$el.find('.bottom-layout-bottoms').each(function (e) {
                    $(this).on('mouseenter', function (e) {
                        e.stopPropagation();
                        $(this).css('opacity', 1)
                    }.bind(this)).on('mouseleave', function (e) {
                        e.stopPropagation();
                        $(this).css('opacity', 0)
                    }.bind(this));
                });
            });

            this.models.forEach(model => {
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
                    })
                })
            });

            this.getModels().forEach(model => {
                this.createView(model.id + 'Action', this.recordActionView, {
                    el: this.options.el + ` [data-id="${model.id}"] .inline-actions`,
                    model: model.item,
                    scope: this.itemScope,
                    showIcons: true,
                    parentModelName: this.selectionModel.name,
                    relationName: this.relationName
                })
            });

            this.listenTo(this, 'refresh', () => {
                let view = this.getParentView();
                if (view) {
                    this.notify('Loading..')
                    view.refresh();
                }
            })
        },

        prepareAndExecuteAction(data, callback) {
            let model = this.getModels().find(m => m.item.id === data.id);
            if (!model) {
                return;
            }
            let itemModel = model.item;
            let self = Espo.utils.clone(this);
            self.model = this.selectionModel;
            self.link = this.relationName;
            self.getModel = () => {
                let model = this.getModels().find(m => m.item.id === data.id);
                if (!model) {
                    return;
                }
                return model.item;
            }
            this.getCollectionFactory().create(this.itemScope, (collection) => {
                self.collection = collection;
                self.collection.add(itemModel);
                self.collection.url = this.selectionModel.name + '/' + this.selectionModel.id + '/' + this.relationName;
                callback(self);
            });
        },

        actionUnlinkRelated(data) {
            this.prepareAndExecuteAction(data, (self) => {
                Relationship.prototype.actionUnlinkRelated.call(self, data);
            });
        },

        actionRemoveRelated(data) {
            this.prepareAndExecuteAction(data, (self) => {
                self.isHierarchical = () => false;
                Relationship.prototype.actionRemoveRelated.call(self, data);
            });
        },

        actionUniversalAction(data) {
            this.prepareAndExecuteAction(data, (self) => {
                Relationship.prototype.actionUniversalAction.call(self, data);
            });
        },

        data() {
            let columns = this.getColumns();
            return {
                title: '',
                columns: columns,
                columnLength: columns.length,
                size: 100 / columns.length,
                showOverlay: this.showOverlay,
                overlayLogo: this.getFavicon()
            };
        },

        getRecordButtons() {
            return this.getParentView().getCompareButtons();
        },

        canLoadActivities() {
            return true;
        },

        getSvelteSideViewProps(parentView) {
            let thisClone = Espo.utils.clone(this);

            thisClone.scope = 'Selection';
            thisClone.model = this.selectionModel;
            thisClone.mode = 'detail';

            let option = Detail.prototype.getSvelteSideViewProps.call(thisClone, parentView);

            option.showInsights = true;
            option.isCollapsed = false;

            return option;
        },

        getColumns() {
            let columns = [];

            this.models.forEach((model) => {
                let hasName = model.hasField(model.nameField)
                return columns.push({
                    id: model.id,
                    entityType: model.name,
                    selectionItemId: model.get('_selectionItemId'),
                    action: model.id + 'Action',
                    label: this.getModelTitle(model) ?? model.get('id'),
                    name: `<a href="#/${model.name}/view/${model.id}"  target="_blank" title="${this.getModelTitle(model)}"> ${hasName ? (this.getModelTitle(model) ?? 'None') : model.get('id')} </a>`,
                });
            });

            return columns;
        },

        getModels() {
            return this.models;
        },

        afterRender() {
            let count = 0;
            this.models.forEach(m => {
                if (this.getAcl().check('LayoutProfile', 'read')) {
                    this.createView(m.id + 'layoutConfiguratorSelection', "views/record/layout-configurator", {
                        scope: m.name,
                        viewType: 'selection',
                        label: this.translate('Fields'),
                        layoutData: this.layoutData[m.name].layoutData,
                        el: this.options.el + ` td[data-id="${m.id}"] .layout-editor-container.selection`,
                    }, (view) => {
                        view.render()
                        view.on("refresh", () => this.getParentView().refreshContent());
                    });

                    this._helper.layoutManager.get(this.model.name, 'selectionRelations', null, (data) => {
                        this.createView(m.id + 'layoutConfiguratorSelectionRelation', "views/record/layout-configurator", {
                            scope: m.name,
                            viewType: 'selectionRelations',
                            label: this.translate('Relations'),
                            layoutData: data,
                            el: this.options.el + ` td[data-id="${m.id}"] .layout-editor-container.relations`,
                            alignRight: true
                        }, (view) => {
                            view.render()
                            view.on("refresh", () => this.getParentView().refreshContent());

                        });
                    })
                }

                this.createView(m.id, this.detailComparisonView, {
                    el: this.options.el + ` td[data-id="${m.id}"] .record-content`,
                    scope: m.name,
                    mode: 'detail',
                    model: m,
                    detailLayout: this.layoutData[m.name].detailLayout,
                    bottomView: 'views/selection/record/detail-bottom-comparison'
                }, view => {
                    view.render(() => {
                        count++;
                        if (count === this.models.length) {
                            this.trigger('detailPanelsLoaded', {list: []});
                            this.trigger('all-panels-rendered')
                            this.$el.find('.overlay').addClass('hidden');
                        }
                    });

                    this.listenTo(this.model, 'sync', () => {
                        view.reRender();
                    });
                });
            });
        }
    });
});
