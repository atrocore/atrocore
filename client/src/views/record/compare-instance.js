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

        getDistantModels() {
            return this.distantModels;
        },

        getModels() {
            return [this.model, ...this.distantModels];
        }
    });
});