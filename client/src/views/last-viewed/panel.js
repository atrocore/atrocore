/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/last-viewed/panel', 'view', function (Dep) {
    return Dep.extend({
        template: 'last-viewed/panel',

        loadingGroups: false,

        groups: [],

        events: {
            'click [data-action="showMore"]': function (e) {
                $(e.currentTarget).addClass('disabled');
                $(e.currentTarget).parent().find('img').css('display', 'block');

                this.fetchCollectionGroups(() => {
                    this.reRender()
                }, this.collection.length)

            }
        },

        setup() {
            this.groups = [];
            this.collection = null;
            this.getCollectionFactory().create('ActionHistoryRecord', collection => {
                this.collection = collection;
                this.collection.maxSize = this.getConfig().get('lastViewedCount') || 20;
                this.collection.sortBy = 'createdAt';
                this.collection.asc = true;
                this.collection.url = 'LastViewed';
                this.loadingGroups = true
                this.once('after:render', () => {
                    this.fetchCollectionGroups(() => {
                        this.loadingGroups = false
                        this.reRender()
                    })
                })
            });
        },

        data() {
            return {
                groups: this.groups,
                loadingGroups: this.loadingGroups,
                showMoreActive: this.canLoadMore()
            };
        },

        canLoadMore() {
            return this.collection.length && (this.collection.length < this.collection.total);
        },

        fetchCollectionGroups(callback, offset = 0) {
            this.ajaxGetRequest('LastViewed', {
                maxSize: this.getConfig().get('lastViewedCount') || 20,
                offset: offset,
            }).then(data => {
                let result = {};
                data.list.forEach(item => {
                    if (!result[item.controllerName]) {
                        result[item.controllerName] = {
                            key: item.controllerName,
                            collection: [],
                            rowList: []
                        }
                    }

                    result[item.controllerName].collection.push({
                        entityId: item.targetId,
                        entityName: item.targetName,
                        entityType: item.controllerName
                    });

                    result[item.controllerName].rowList.push(item.targetId)
                });

                if (this.groups.length) {
                    let keys = this.groups.map(group => group.key);
                    Object.values(result).forEach((el) => {
                        if (!keys.includes(el.key)) {
                            this.groups.push(el)
                        } else {
                            this.groups.forEach((group, key) => {
                                if (el.key === group.key) {
                                    this.groups[key].collection = [...group.collection, ...el.collection];
                                    this.groups[key].rowList = [...group.rowList, ...el.rowList];
                                }
                            })
                        }
                    })
                } else {
                    this.groups = Object.values(result)
                }

                if (!this.getConfig().get('tabIconsDisabled')) {
                    this.groups.forEach((group, key) => {
                        let icon = this.getTabIcon(this.groups[key].key);

                        if (!icon) {
                            icon = this.getDefaultTabIcon(this.groups[key].key);
                        }

                        this.groups[key].icon = icon;
                    });
                }

                this.collection.total = data.total;
                let length = 0;
                this.groups.forEach(group => {
                    length += group.collection.length;
                })
                this.collection.length = length;

                callback();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.buildGroups();
        },

        buildGroups() {
            if (!this.groups || this.groups.length < 1) {
                return;
            }
            if (this.groups.length === 1) {
                this.$el.find('.group .list-container').css('min-height', '300px')
            }
            this.groups.forEach((group, key) => {
                this.getCollectionFactory().create(group.key, groupCollection => {
                    this.initGroupCollection(group, groupCollection, () => {
                        let viewName = 'views/last-viewed/record/list';
                        let options = {
                            collection: groupCollection,
                            listLayout: {
                                rows: [
                                    [
                                        {
                                            name: "name",
                                            link: true,
                                            notSortable: true
                                        }
                                    ]
                                ]
                            },
                            el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                        };

                        this.createView('bookmark' + group.key, viewName, options, view => {
                            view.render();
                        });
                    });
                });
            });
        },

        initGroupCollection(group, groupCollection, callback) {
            groupCollection.url = group.key;
            groupCollection.maxSize = group.collection.length;
            groupCollection.total = group.collection.length;
            groupCollection.sortBy = 'name';
            groupCollection.data.select = 'id,name'

            group.collection.forEach(item => {
                this.getModelFactory().create(group.key, model => {
                    model.set({
                        id: item.entityId,
                        name: item.entityName
                    });
                    groupCollection.add(model);
                });

                this.getModelFactory().create('ActionHistoryRecord', model => {
                    if (this.collection.get(item.id)) {
                        this.collection.remove(item.id);
                    }
                    model.set({
                        controllerName: item.entityName,
                        targetId: item.entityId,
                        targetName: item.targetName
                    });
                    this.collection.add(model);
                })
            });
            callback();
        },

    });
});
