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

                if (this.getAcl().check(this.model.get('targetEntityId'), 'edit')) {
                    this.additionalButtons.push({
                        name: 'applyToRecords',
                        label: this.translate('applyToRecords', 'labels', 'DataPipeline'),
                        action: 'applyToRecords'
                    });
                }
            }
        },

        actionTransformationPreview() {
            this.createView('dataPipelineCompare', 'views/data-pipeline/modals/compare', {
                sourceScope: this.model.get('sourceEntityId'),
                targetScope: this.model.get('targetEntityId'),
                fixedTarget: true,
                sourceSelectable: true
            }, dialog => dialog.render());
        },

        actionApplyToRecords() {
            const scope = this.model.get('sourceEntityId');
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('applyToRecords', viewName, {
                scope: scope,
                headerText: `${this.translate('applyToRecords', 'labels', 'DataPipeline')}: ${this.translate(scope, 'scopeNamesPlural')}`,
                multiple: true,
                createButton: false,
                massRelateEnabled: true
            }, dialog => {
                dialog.render(() => this.notify(false));
                this.listenToOnce(dialog, 'select', selected => {
                    const where = selected.massRelate
                        ? (selected.where || [])
                        : [{type: 'in', attribute: 'id', value: selected.map(model => model.id)}];

                    this.notify(this.translate('pleaseWait', 'messages'));
                    this.ajaxPostRequest('DataPipeline/transformAsync', {
                        entityName: scope,
                        targetEntityName: this.model.get('targetEntityId'),
                        where
                    }).then(() => {
                        Espo.Ui.success(this.translate('massActionDelegatedToJm'));
                    });
                });
            });
        }
    });
});
