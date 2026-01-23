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

        entityTypeField: 'masterEntity',

        recordView: 'views/cluster/record/detail',

        getEntityTypes() {
            let entities = [this.model.get('masterEntity')];
            this.getStagingEntities(this.model.get(this.entityTypeField)).forEach(stagingEntity => {
                entities.push(stagingEntity);
            });
            return entities;
        },

        shouldOpenSelectDialog() {
            return true;
        },

        getItemsUrl(clusterId) {
            return `cluster/${clusterId}/clusterItems?select=entityName,entityId,entity&collectionOnly=true&sortBy=createdAt&asc=false&offset=0&maxSize=20`;
        },

        selectRecord(foreignScope) {
            let viewName = this.getMetadata().get('clientDefs.' + foreignScope + '.modalViews.select') || 'views/modals/select-records';
            this.notify('Loading...');
            this.createView('selectItems', viewName, {
                scope: foreignScope,
                createButton: false,
            }, view => {
                view.render();
                this.notify(false);
                this.listenToOnce(view, 'select', function (model) {
                    this.clearView('selectRecords');
                    this.ajaxPostRequest('ClusterItem', {
                        entityName: foreignScope,
                        entityId: model.id,
                        clusterId: this.model.id
                    }).then(() => {
                        this.model.trigger('after:relate', this.link);
                        if (this.toggleSelected(model.id)) {
                            window.leftSidePanel?.setSelectedIds(this.selectedIds);
                        }
                    })
                }, this);
            });
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
                    label: this.translate('addItem'),
                    dropdownItems: this.getDropdownItems()
                })
            }

            return buttons;
        },
    })
});

