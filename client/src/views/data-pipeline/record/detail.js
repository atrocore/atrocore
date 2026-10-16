/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/data-pipeline/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            if (!this.model.isNew()) {
                this.additionalButtons.push({
                    name: 'transformationPreview',
                    label: this.translate('transformationPreview', 'labels', 'DataPipeline'),
                    action: 'transformationPreview'
                });
            }
        },

        actionTransformationPreview() {
            this.createView('dataPipelineCompare', 'views/data-pipeline/modals/compare', {
                sourceScope: this.model.get('sourceEntityId'),
                targetScope: this.model.get('targetEntityId'),
                fixedTarget: true,
                sourceSelectable: true
            }, dialog => dialog.render());
        }
    });
});
