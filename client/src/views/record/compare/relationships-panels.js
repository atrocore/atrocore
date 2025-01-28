/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationships-panels', 'view', function (Dep) {
    return Dep.extend({
        template: 'record/compare/relationships-panels',

        relationshipView: 'views/record/compare/relationship',

        relationshipsPanels: [],

        columns: [],

        setup() {
            Dep.prototype.setup.call(this);
            this.scope = this.options.scope;
            this.collection = this.options.collection;
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.columns = this.options.columns
            this.model = this.options.model;
            this.relationshipsPanels = this.options.relationshipsPanels;
            this.instances = this.getMetadata().get(['app', 'comparableInstances'])
            this.nonComparableFields = this.getMetadata().get(['scopes', this.scope, 'nonComparableFields']) ?? [];
            this.distantModels = this.options.distantModels ?? [];
            this.renderedPanels = [];

            if (this.instanceComparison) {
                this.relationshipView = 'views/record/compare/relationship-instance';
            }

            this.listenTo(this, 'after:render', () => {
                this.renderedPanels = [];
                this.relationshipsPanels.forEach(panelData => {
                    let data = Espo.Utils.clone(panelData);

                    delete data['defs'];

                    let o = {
                        el: this.options.el + ` [data-panel="${panelData.name}"] `,
                        relationship: data,
                        model: this.model,
                        scope: this.scope,
                        instanceComparison: this.instanceComparison,
                        models: this.options.models,
                        distantModels: this.distantModels,
                        collection: this.collection,
                        columns: this.columns,
                        defs: panelData.defs,
                        merging: this.options.merging
                    }
                    let relationshipView = '';
                    if (this.instanceComparison) {
                        relationshipView = panelData.defs.compareInstanceRecordsView
                            ?? this.getMetadata().get(['clientDefs', this.scope, 'relationshipPanels', panelData.name, 'compareInstanceRecordsView'])
                            ?? this.relationshipView;

                    } else {
                        relationshipView = panelData.defs.compareRecordsView
                            ?? this.getMetadata().get(['clientDefs', this.scope, 'relationshipPanels', panelData.name, 'compareRecordsView'])
                            ?? this.relationshipView;
                    }

                    this.createView(panelData.name, relationshipView, o, view => {
                          view.render();
                          if(view.deferRendering) {
                              this.listenTo(view, 'all-fields-rendered', () => {
                                  this.handlePanelRendering(panelData.name);
                              });
                          }else{
                              if(view.isRendered()) {
                                  this.handlePanelRendering(panelData.name);
                              }
                              view.once('after:render', () => {
                                  this.handlePanelRendering(panelData.name);
                              });
                          }

                    }, false);
                })
            })
        },

        data() {
            return {
                scope: this.scope,
                relationshipsPanels: this.relationshipsPanels.map(v => {
                    return {
                        label: v.label,
                        name: v.name
                    }
                }),
                columns: this.columns,
                itemColumnCount: 1,
                columnsLength: this.columns.length
            }
        },

        fetch() {
            let attributes = {}
            this.relationshipsPanels.forEach(panelData => {
                let view = this.getView(panelData.name);
                if(!view){
                    return;
                }
                if (!attributes[panelData.link]) {
                    attributes[panelData.link] = view.fetch();
                    return;
                }

                let data = view.fetch();
                attributes[panelData.link].toUpsert = [...attributes[panelData.link].toUpsert, ...data.toUpsert];
                attributes[panelData.link].toDelete = [...attributes[panelData.link].toDelete, ...data.toDelete];

            });

            return attributes;
        },

        validate() {
            let validate = false;
            this.relationshipsPanels.forEach(panelData => {
                let view = this.getView(panelData.name);
                if(!view){
                    return;
                }

                validate = validate || view.validate();
            });

            return validate;
        },

        handlePanelRendering(name) {
            this.renderedPanels.push(name);
            if(this.renderedPanels.length === this.relationshipsPanels.length) {
                this.trigger('all-panels-rendered');
                this.renderedPanels = [];
            }
        },

        changeViewMode(mode) {
            this.relationshipsPanels.forEach(panelData => {
                let view = this.getView(panelData.name);
                if(!view){
                    return;
                }

               view.changeViewMode(mode);
            });
        }
    })
})