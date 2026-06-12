/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/cluster/detail', ['views/selection/detail', 'views/record/panels/relationship', 'collection'], function (Dep, Relationship, Collection) {

    return Dep.extend({

        availableModes: ['standard', 'compare', 'merge'],

        scope: 'Cluster',

        link: 'clusterItems',

        inverseLink: 'cluster',

        itemScope: 'ClusterItem',

        entityTypeField: 'masterEntity',

        recordView: 'views/cluster/record/detail',

        rejectedItems: [],

        hasMoreByEntityType: {},

        offsetByEntityType: {},

        loadingMoreByEntityType: {},

        itemsPageSize: 20,

        getEntityTypes() {
            if (!this.model.get(this.entityTypeField)) {
                return []
            }

            let entities = [this.model.get(this.entityTypeField)];
            this.getStagingEntities(this.model.get(this.entityTypeField)).forEach(stagingEntity => {
                entities.push(stagingEntity);
            });

            return entities;
        },

        reloadStyle(selected = null) {
            Dep.prototype.reloadStyle.call(this);

            selected = selected ?? this.selectionViewMode;
            if (selected === 'standard') {
                $(`.compare-mass-action[data-name="massAction"]`).addClass('hidden')
            }
        },

        shouldOpenSelectDialog() {
            return true;
        },

        reloadModels(callback) {
            const previousOffsets = { ...this.offsetByEntityType };
            this.hasMoreByEntityType = {};
            this.offsetByEntityType = {};

            const entityTypes = this.getEntityTypes();
            const perTypePromises = entityTypes.map(entityType => {
                const loadedCount = previousOffsets[entityType] || this.itemsPageSize;
                const whereRelation = JSON.stringify([{ attribute: 'entityName', type: 'equals', value: entityType }]);
                return this.loadSelectionItemModels(
                    `entityRelation?entityName=Cluster&link=clusterItems&id=${this.model.id}&select=entityName,entityId,entity,confirmedAutomatically,matchedScore&collectionOnly=true&sortBy=id&asc=false&offset=0&maxSize=${loadedCount + 1}&where=${encodeURIComponent(whereRelation)}`
                ).then(models => {
                    if (models.length > loadedCount) {
                        models = models.slice(0, loadedCount);
                        this.hasMoreByEntityType[entityType] = true;
                    } else {
                        this.hasMoreByEntityType[entityType] = false;
                    }
                    this.offsetByEntityType[entityType] = loadedCount;
                    return models;
                });
            });

            Promise.all(perTypePromises).then(allModels => {
                let models = allModels.flat();

                if (models.length > 0) {
                    this.selectionItemModels = models;
                    let allIds = models.map(m => m.id);
                    // remove dead Ids
                    this.hiddenIds = this.hiddenIds.filter(id => allIds.includes(id));

                    for (const id of allIds.reverse()) {
                        if ((allIds.length - this.hiddenIds.length) <= this.maxForComparison) {
                            break;
                        }

                        if (this.hiddenIds.includes(id)) {
                            continue;
                        }

                        this.hiddenIds.push(id);
                    }
                }

                this.loadSelectionItemModels(`entityRelation?entityName=Cluster&link=rejectedClusterItems&id=${this.model.id}&select=entityName,entity,entityId&collectionOnly=true&sortBy=id&asc=false&offset=0&maxSize=${this.itemsPageSize}`)
                    .then(models => {
                        this.rejectedItems = models;
                        if (window.itemsListPanel) {
                            window.itemsListPanel?.setRecords(this.getRecordForPanels());
                            window.itemsListPanel?.setSelectedIds(this.getSelectedIds());
                            window.itemsListPanel?.setHasMoreByType({ ...this.hasMoreByEntityType });
                        }
                    });

                if (callback) {
                    callback();
                }
            });
        },

        loadMoreForEntityType(entityType) {
            const loading = { ...this.loadingMoreByEntityType, [entityType]: true };
            this.loadingMoreByEntityType = loading;
            if (window.itemsListPanel) {
                window.itemsListPanel?.setLoadingMoreByType({ ...this.loadingMoreByEntityType });
            }

            const offset = this.offsetByEntityType[entityType] || 0;
            const where = JSON.stringify([{ attribute: 'entityName', type: 'equals', value: entityType }]);

            this.loadSelectionItemModels(
                `entityRelation?entityName=Cluster&link=clusterItems&id=${this.model.id}&select=entityName,entityId,entity,confirmedAutomatically,matchedScore&collectionOnly=true&sortBy=id&asc=false&offset=${offset}&maxSize=${this.itemsPageSize + 1}&where=${encodeURIComponent(where)}`
            ).then(models => {
                let hasMore = false;
                if (models.length > this.itemsPageSize) {
                    models = models.slice(0, this.itemsPageSize);
                    hasMore = true;
                }

                this.hasMoreByEntityType[entityType] = hasMore;
                this.offsetByEntityType[entityType] = offset + this.itemsPageSize;
                this.loadingMoreByEntityType = { ...this.loadingMoreByEntityType, [entityType]: false };

                const newIds = models.map(m => m.id);
                this.hiddenIds.push(...newIds);
                this.selectionItemModels = [...this.selectionItemModels, ...models];

                if (window.itemsListPanel) {
                    window.itemsListPanel?.setRecords(this.getRecordForPanels());
                    window.itemsListPanel?.setSelectedIds(this.getSelectedIds());
                    window.itemsListPanel?.setHasMoreByType({ ...this.hasMoreByEntityType });
                    window.itemsListPanel?.setLoadingMoreByType({ ...this.loadingMoreByEntityType });
                }
            });
        },

        getRecordForPanels() {
            if (!this.selectionItemModels) {
                return [];
            }
            let records = this.selectionItemModels.map(model => {
                return {
                    id: model.id,
                    name: this.getModelTitle(model),
                    entityType: model.name,
                    confirm: model.item?.get('_meta')?.cluster?.confirmed ?? false,
                    confirmedAutomatically: model.item?.get('confirmedAutomatically') ?? false,
                    rejected: false
                }
            });

            for (const model of this.rejectedItems || []) {
                records.push({
                    id: model.id,
                    name: this.getModelTitle(model),
                    entityType: model.name,
                    confirm: false,
                    confirmedAutomatically: false,
                    rejected: true
                });
            }

            return records;
        },

        createItemListPanel(element) {
            if (window.itemsListPanel) {
                try {
                    window.itemsListPanel.$destroy();
                } catch (e) {
                }
            }

            window.itemsListPanel = new Svelte.ClusterItemList({
                target: element,
                props: {
                    records: this.getRecordForPanels(),
                    selectedIds: this.getSelectedIds(),
                    selectionViewMode: this.selectionViewMode,
                    hasMoreByType: { ...this.hasMoreByEntityType },
                    loadingMoreByType: { ...this.loadingMoreByEntityType },
                    onLoadMoreForType: (entityType) => this.loadMoreForEntityType(entityType),
                    onMountRowActions: (el, itemId, relationName) => {
                        const model = [...(this.selectionItemModels || []), ...(this.rejectedItems || [])]
                            .find(m => m.id === itemId);
                        if (!model) return;
                        this.createView('rowActions_' + itemId, 'views/record/row-actions/relationship', {
                            el: el,
                            model: model.item,
                            parentModelName: 'Cluster',
                            relationName: relationName
                        }, view => {
                            view.render();
                        });
                    },
                    onItemClicked: (e, itemId) => {
                        if (this.selectionViewMode === 'standard') {
                            return;
                        }

                        e.preventDefault();

                        if (this.toggleSelected(itemId)) {
                            window.itemsListPanel?.setSelectedIds(this.getSelectedIds());
                            if (this.getView('record')) {
                                this.getView('record').showLoader();
                            }
                            this.trigger('refresh', { noReload: true });
                        }
                    },
                    onSelectAll: (entityType) => {
                        let shouldReload = false;
                        this.selectionItemModels.forEach(model => {
                            if (model.name === entityType && this.hiddenIds.includes(model.id)) {
                                if (this.toggleSelected(model.id)) {
                                    shouldReload = true;
                                }
                            }
                        });

                        if (shouldReload) {
                            if (this.getView('record')) {
                                this.getView('record').showLoader();
                            }
                            window.itemsListPanel?.setSelectedIds(this.getSelectedIds());
                            this.trigger('refresh', { noReload: true });
                        }
                    },
                    onUnSelectAll: (entityType) => {
                        let shouldReload = false;
                        this.selectionItemModels.reverse().forEach(model => {
                            if (model.name === entityType && !this.hiddenIds.includes(model.id)) {
                                if (this.toggleSelected(model.id)) {
                                    shouldReload = true;
                                }
                            }
                        });

                        if (shouldReload) {
                            if (this.getView('record')) {
                                this.getView('record').showLoader();
                            }
                            window.itemsListPanel?.setSelectedIds(this.getSelectedIds());
                            this.trigger('refresh', { noReload: true });
                        }
                    }
                }
            });

            $(element).on('click', '[data-action]', (e) => {
                var $el = $(e.currentTarget);
                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);

                if (typeof Relationship.prototype[method] == 'function') {
                    var data = $el.data();
                    let model = this.selectionItemModels.find(m => m.item.id === data.id);
                    if (!model) {
                        model = this.rejectedItems.find(m => m.item.id === data.id);
                    }
                    let thisClone = Espo.utils.clone(this);
                    let collection = new Collection();
                    collection.add(model.item);
                    thisClone.collection = collection;
                    thisClone['getModel'] = (data, evt) => {
                        if (data.cid) {
                            return thisClone.collection.get(data.cid)
                        }
                        return thisClone.collection.get(data.id)
                    };

                    Relationship.prototype[method].call(thisClone, data, e);

                    e.preventDefault();
                }
            });
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
                            window.itemsListPanel?.setSelectedIds(this.selectedIds);
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
                additionalButtons: [
                    {
                        name: 'massAction',
                        className: 'hidden'
                    }
                ],
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
            if (['empty', 'invalid'].includes(this.model.get('state'))) {
                return false;
            }

            if (this.selectionViewMode === 'standard') {
                return !(!this.collection || this.collection.models.length <= 1);
            }

            return (!(!this.selectionItemModels || this.selectionItemModels.length <= 1));
        }
    })
});

