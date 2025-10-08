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
        },

        getOtherModelsForComparison(currentModel) {
            return [this.versionModel];
        },

        buildComparisonTableHeaderColumn() {
            let columns = [];
            columns.push({ name: this.translate('Versions', 'labels'), isFirst: true });
            columns.push({ name: `<a href="#/${this.scope}/view/${this.model.id}" target="_blank"> ${this.translate('Current', 'labels')}</a>` });
            columns.push({
                name: this.getParentView().currentVersion,
            })
            return columns;
        },

        getModelsForAttributes() {
            return [this.model]
        },

        getModels() {
            return [this.model, this.versionModel];
        }
    });
});