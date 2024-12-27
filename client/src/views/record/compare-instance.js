/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare-instance','views/record/compare', function (Dep) {

    return Dep.extend({
        instanceComparison: true,

        distantModels: [],

        init() {
            Dep.prototype.init.call(this);
            this.instances = this.options.instances || this.getMetadata().get(['app', 'comparableInstances']);
            this.distantModels = this.options.distantModels ?? [];
        },

        getOtherModelsForComparison(currentModel) {
            return this.distantModels;
        },

        buildComparisonTableHeaderColumn() {
            let columns = [];
            columns.push({name: this.translate('instance', 'labels', 'Synchronization'), isFirst: true});
            columns.push({name: `<a href="#/${this.scope}/view/${this.model.id}" target="_blank"> ${this.translate('current', 'labels', 'Synchronization')}</a>`});
            this.instances.forEach(instance => {
                columns.push({
                    name: `<a href="${instance.atrocoreUrl}#/${this.scope}/view/${this.model.id}" target="_blank"> ${instance.name}</a>`,
                    _error: instance._error
                })
            });
            return columns;
        },

        setupFieldsPanels() {
            this.createView('fieldsPanels', this.fieldsPanelsView, {
                scope: this.scope,
                model: this.model,
                fieldList: this.fieldsArr,
                instances: this.instances,
                columns: this.buildComparisonTableHeaderColumn(),
                instanceComparison: this.instanceComparison,
                models: [this.model, ...this.distantModels],
                merging: this.merging,
                el: `${this.options.el} [data-panel="fields-overviews"] .list-container`
            }, view => {
                view.render();
            })
        },

        setupRelationshipsPanels() {
            this.notify('Loading...');
            this.createView('relationshipsPanels', this.relationshipsPanelsView, {
                scope: this.scope,
                model: this.model,
                relationshipsPanels: this.getRelationshipPanels(),
                models: [this.model, ...this.distantModels],
                distantModels: this.distantModels,
                instanceComparison: true,
                columns: this.buildComparisonTableHeaderColumn(),
                el: `${this.options.el} .compare-panel[data-name="relationshipsPanels"]`
            }, view => {
                this.notify(false)
                view.render();
            })
        },
    });
});