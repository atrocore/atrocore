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

        availableModes: ['standard', 'compare', 'merge'],

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
            return `cluster/${clusterId}/clusterItems?select=entityName,entityId,entity,confirmedAutomatically,matchedScore&collectionOnly=true&sortBy=id&asc=false&offset=0&maxSize=20`;
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
            if (['compare', 'merge'].includes(this.selectionViewMode)) {
                return 'views/cluster/record/compare';
            }

            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        getCompareButtons() {
            return {
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
        },

        getStagingEntities(masterEntity) {
            let result = [];
            _.each(this.getMetadata().get(['scopes']), (scopeDefs, scope) => {
                if (scopeDefs.primaryEntityId === masterEntity && scopeDefs.role !== 'changeRequest') {
                    result.push(scope);
                }
            })
            return result;
        },

        isActiveMerge() {
            return true;
        },


        canMerge() {
            if (this.selectionViewMode === 'standard') {
                if ((!this.collection || this.collection.models.length <= 1)) {
                    return false;
                }

                let confirmed = [];
                this.collection.models.forEach(item => {
                    if (item.get('_meta')?.cluster?.confirmed) {
                        confirmed.push(item);
                    }
                });

                return confirmed.length >= 2;
            }


            if ((!this.selectionItemModels || this.selectionItemModels.length <= 1)) {
                return false;
            }

            let confirmed = [];
            this.selectionItemModels.forEach(model => {
                if (model.item.get('_meta')?.cluster?.confirmed) {
                    confirmed.push(model);
                }
            });

            return confirmed.length >= 2;
        },

        reloadModels(callback) {
            Dep.prototype.reloadModels.call(this, () => {
                if (this.selectionViewMode === 'merge') {
                    this.selectionItemModels.forEach(model => {
                        if (model.item.get('_meta')?.cluster?.confirmed === false) {
                            this.hiddenIds.push(model.id);
                        }
                    });
                }

                callback();
            })
        },

        toggleSelected(itemId) {
            if(this.selectionViewMode === 'merge') {
                let model = this.selectionItemModels.find(m => m.id === itemId);
                if (model.item.get('_meta')?.cluster?.confirmed === false) {
                    this.notify(this.translate('cannotMergeUnconfirmedItems', 'messages', 'Cluster'), 'error');
                    return;
                }
            }

            Dep.prototype.toggleSelected(itemId);
        }
    })
});

