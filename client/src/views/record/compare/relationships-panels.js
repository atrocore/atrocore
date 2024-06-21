/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationships-panels','view', function (Dep) {
    return Dep.extend({
        template: 'record/compare/relationships-panels',
        relationshipView: 'views/record/compare/relationship',
        fieldListPanels: [],
        relationshipsPanels: [],
        setup() {
            Dep.prototype.setup.call(this);
            this.scope = this.options.scope;
            this.relationships = this.options.relationships
            this.model = this.options.model;
            this.nonComparableFields = this.getMetadata().get(['scopes', this.scope, 'nonComparableFields']) ?? [];

            this.notify('loading')
            this.relationships.forEach(relationship => {
                if(this.nonComparableFields.includes(relationship.name)){
                    return;
                }
                let relationScope = this.getMetadata().get(['entityDefs', this.scope, 'links', relationship.name, 'entity'])
                if(!relationScope){
                    return;
                }
                let panelData = {
                    label: this.translate(relationship.name, 'links', this.scope),
                    scope: relationScope,
                    name: relationship.name,
                };
                this.relationshipsPanels.push(panelData);
            })



            this.listenTo(this, 'after:render', () => {
                this.relationshipsPanels.forEach(panelData => {
                    let o = {
                        el: this.options.el + ` [data-panel="${panelData.name}"] `,
                        relationship: panelData,
                        model: this.model.clone(),
                        scope: this.scope
                    }
                    this.relationshipView = this.getMetadata().get(['clientDefs', this.scope, 'relationshipPanels', panelData.name, 'compareRecordsView']) ?? this.relationshipView;
                    this.createView(panelData.name, this.relationshipView, o , view =>{
                        view.render();
                    })
                })
            })
        },
        data(){
            return {
                scope: this.scope,
                relationshipsPanels: this.relationshipsPanels,
                distantModels: this.options.distantModels
            }
        },
    })
})