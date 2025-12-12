/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare-entities', ['view', 'views/record/detail', 'views/selection/record/detail/compare'], function (Dep, Detail, Compare) {

    return Dep.extend({

        template: 'selection/record/compare-entities',

        models: [],

        detailComparisonView: 'views/selection/record/detail/detail-comparison-view',

        showOverlay: true,

        events: {
            'click a.swap-entity': function (e) {
                Compare.prototype.afterSwapButtonClick.call(this, e)
            },
            'click  a.remove-entity': function (e) {
                Compare.prototype.afterRemoveButtonClicked.call(this, e);
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
                this.$el.find('th').each(function (e) {

                    $(this).on('mouseenter', function (e) {
                        e.stopPropagation();
                        $(this).find('div.inline-actions').removeClass('hidden')
                    }.bind(this)).on('mouseleave', function (e) {
                        e.stopPropagation();
                        $(this).find('div.inline-actions').addClass('hidden')
                    }.bind(this));
                });
            });

            this.models.forEach(model => {
                this.listenTo(model, 'before:save', (attrs) => {
                    $.each(attrs, (name, value) => {
                        if(!model.defs['fields'][name]) {
                            return;
                        }
                        if(model.defs['fields'][name].attributeId) {
                            if(!attrs['__attributes']) {
                                attrs['__attributes'] = [model.defs['fields'][name].attributeId];
                            }else{
                                attrs['__attributes'].push([model.defs['fields'][name].attributeId]);
                            }
                        }
                    })
                })
            })
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
                let hasName = !!this.getMetadata().get(['entityDefs', model.name, 'fields', 'name', 'type'])
                return columns.push({
                    id: model.id,
                    entityType: model.name,
                    selectionRecordId: model.get('_selectionRecordId'),
                    label: model.get('name') ?? model.get('id'),
                    name: `<a href="#/${model.name}/view/${model.id}"  target="_blank" title="${model.get('name')}"> ${hasName ? (model.get('name') ?? 'None') : model.get('id')} </a>`,
                });
            });

            return columns;
        },

        afterRender() {
            let count = 0;
            this.models.forEach(m => {
                this.createView('layoutConfigurator', "views/record/layout-configurator", {
                    scope: m.name,
                    viewType: 'selection',
                    layoutData: this.layoutData[m.name].layoutData,
                    el: this.options.el +  `th[data-id="${m.id}"] .layout-editor-container`,
                }, (view) => {
                    view.render()
                    view.on("refresh", () => this.getParentView().refreshContent());
                });
                this.createView(m.id, this.detailComparisonView, {
                    el: this.options.el + ` .record-content[data-id="${m.id}"]`,
                    scope: m.name,
                    mode: 'detail',
                    model: m,
                    detailLayout: this.layoutData[m.name].detailLayout,
                    bottomView:  'views/selection/record/detail-bottom-comparison'
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
