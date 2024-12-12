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
            this.relationships = this.options.relationships
            this.collection = this.options.collection;
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.columns = this.options.columns
            this.model = this.options.model;
            this.relationshipsPanels = [];
            this.instances = this.getMetadata().get(['app', 'comparableInstances'])
            this.nonComparableFields = this.getMetadata().get(['scopes', this.scope, 'nonComparableFields']) ?? [];

            if (this.instanceComparison) {
                this.relationshipView = 'views/record/compare/relationship-instance';
            }


            if ('distantModelsAttribute' in this.options && this.instanceComparison) {
                this.distantModelsAttribute = this.options.distantModelsAttribute;
            }


            let bottomPanels = this.getMetadata().get(['clientDefs', this.scope, 'bottomPanels', 'detail']) || [];

            this.relationships.forEach(relationship => {
                if (this.nonComparableFields.includes(relationship.name)) {
                    return;
                }

                let relationDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', relationship.name]) ?? {};
                let relationScope = relationDefs['entity'];

                let bottomPanelOptions = bottomPanels.find(panel => panel.name === relationship.name);

                if (!relationScope && !bottomPanelOptions) {
                    return;
                }

                let panelData = {
                    label: this.translate(this.translate(relationship.name), 'links', this.scope),
                    scope: relationScope,
                    name: relationship.name,
                    type: relationDefs['type'],
                    foreign: relationDefs['foreign'],
                    defs: bottomPanelOptions ?? {}
                };
                this.relationshipsPanels.push(panelData);
            })

            this.listenTo(this, 'after:render', () => {
                this.relationshipsPanels.forEach(panelData => {
                    let data = Espo.Utils.clone(panelData);

                    delete data['defs'];

                    let o = {
                        el: this.options.el + ` [data-panel="${panelData.name}"] `,
                        relationship: data,
                        model: this.model,
                        scope: this.scope,
                        instanceComparison: this.instanceComparison,
                        collection: this.collection,
                        columns: this.columns,
                        defs: panelData.defs
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
                    }, false)
                })
            })
        },

        data() {
            return {
                scope: this.scope,
                relationshipsPanels: this.relationshipsPanels,
                instances: this.instances,
                columns: this.columns,
                itemColumnCount: 1,
                columnsLength: this.columns.length + 1
            }
        },
    })
})