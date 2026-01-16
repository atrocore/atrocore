/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/cluster/detail', 'views/selection/detail', function (Dep, Model, List) {

    return Dep.extend({

        availableModes: ['standard', 'compare'],

        scope: 'Cluster',

        link: 'clusterItems',

        inverseLink: 'cluster',

        itemScope: 'ClusterItem',

        getEntityTypes() {
           let entities = [this.model.get('masterEntity')];
            let stagingEntity = this.getStagingEntity(this.model.get('masterEntity'));
            if(stagingEntity) {
                entities.push(stagingEntity);
            }
            return entities;
        },

        hasStaging() {
            return !!this.getStagingEntity(this.model.get('masterEntity'));
        },

        shouldOpenSelectDialog() {
            return this.getEntityTypes().length === 1;
        },

        getItemsUrl(clusterId) {
            return `cluster/${clusterId}/clusterItems?select=entityName,entityId,entity&collectionOnly=true&sortBy=createdAt&asc=false&offset=0&maxSize=20`;
        },

        comparisonAcrossEntities: function () {
            return false;
        },

        getRecordViewName: function () {
            if (this.selectionViewMode === 'compare') {
                return 'views/cluster/record/compare';
            }

            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        getCompareButtons() {
            let buttons = {
                additionalButtons: [],
                buttons: [],
                dropdownButtons: [
                    {
                        label: this.translate('Remove'),
                        name: 'delete'
                    }
                ],
                hasLayoutEditor: true
            }

            if (this.getAcl().check('Cluster', 'edit')) {
                buttons.additionalButtons.push({
                    action: 'addItem',
                    name: 'addItem',
                    label: this.translate('addItem')
                })
            }

            return buttons;
        },
    })
});

