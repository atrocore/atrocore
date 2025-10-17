/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/compare-version', 'views/record/compare', function (Dep) {

    return Dep.extend({

        init() {
            Dep.prototype.init.call(this);
            this.versionModel = this.options.versionModel;
            this.disableModelFetch = true
        },

        getOtherModelsForComparison(currentModel) {
            return [this.versionModel];
        },

        buildComparisonTableHeaderColumn() {
            let columns = [];
            columns.push({ name: this.translate('Versions', 'labels'), isFirst: true });
            columns.push({
                id: this.model.id,
                name: `<a href="#/${this.scope}/view/${this.model.id}" target="_blank"> ${this.translate('Current', 'labels')}</a>`
            });

            const parentView = this.getParentView();
            columns.push({
                id: this.versionModel.id,
                name: parentView.currentVersion,
            })
            return columns;
        },

        getModels() {
            return [this.model, this.versionModel];
        },

        getDefaultModelId() {
            return this.model.id;
        },

        getCompareUrl() {
            return 'RecordVersion/action/merge'
        },

        getCompareData(targetId, attributes, relationshipData) {
            const data = Dep.prototype.getCompareData.call(this, targetId, attributes, relationshipData);

            return {
                ...data,
                scope: this.scope,
                targetId: this.model.id,
                versionName: this.getParentView().currentVersion
            }
        }
    });
});