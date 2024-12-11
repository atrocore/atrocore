/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/bookmark/panel', 'view', function (Dep) {
    return Dep.extend({
        template: 'bookmark/panel',

        loadingGroups: false,

        groups: [],

        rowActionsViewEntity: 'views/bookmark/record/row-actions/bookmarked-entity',

        events: {
            'click [data-action="showMore"]': function (e) {
                $(e.currentTarget).addClass('disabled');
                $(e.currentTarget).parent().find('img').css('display', 'block');

                this.fetchCollectionGroups(() => {
                    this.reRender()
                }, this.collection.length)

            },
            'click [data-action="compare"]': function (e) {
                this.compareEntities($(e.currentTarget).data('key'))
            },
        },

        setup() {
            this.groups = [];
            this.collection = null;
            this.getCollectionFactory().create('Bookmark', collection => {
                this.collection = collection;
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
            this.ajaxGetRequest('Bookmark', {
                maxSize: 10,
                offset: offset,
            }).then(data => {
                if (this.groups.length) {
                    let keys = this.groups.map(group => group.key);
                    data.list.forEach((el) => {
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
                    this.groups = data.list
                }

                this.collection.total = data.total;
                let length = 0;
                this.groups.forEach(group => {
                    length += group.collection.length;
                })
                this.collection.length = length;

                callback();

                this.groups.forEach((group, key) => {
                    let rowActionViewKey = 'rowActionView' + group.key;
                    this.getModelFactory().create('Bookmark', model => {
                        model.set(group.collection[0]);
                        this.createView(rowActionViewKey, this.rowActionsViewEntity, {
                            el: `${this.options.el} .group[data-name="${group.key}"] .action`,
                            key: group.key,
                            model: model,
                            acl: this.options.acl
                        }, view => view.render())
                    })
                })
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
                        let viewName = 'views/bookmark/record/list';
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

                        this.clearView('bookmark' + group.key)
                        this.createView('bookmark' + group.key, viewName, options, view => {
                            view.render();
                            this.listenTo(view, 'unbookmarked-' + group.key, (bookmarkId) => {
                                let bookmark = this.collection.get(bookmarkId)
                                groupCollection.remove(bookmark.get('entityId'));
                                this.groups[key].collection = this.groups[key].collection.filter(item => item.id !== bookmarkId)
                                this.groups[key].rowList = this.groups[key].rowList.filter(id => id !== bookmarkId)
                                view.$el.find('[data-id="' + bookmark.get('entityId') + '"]').remove()
                                if (!this.groups[key].length) {
                                    $(`${this.options.el} .group[data-name="${group.key}"]`).remove()
                                }
                            })
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
            groupCollection.where = [
                {
                    "type": "bool",
                    "value": ['onlyBookmarked']
                }
            ]

            group.collection.forEach(item => {
                this.getModelFactory().create(group.key, model => {
                    model.set({
                        id: item.entityId,
                        name: item.entityName,
                        bookmarkId: item.id
                    });
                    groupCollection.add(model);
                });

                this.getModelFactory().create('Bookmark', model => {
                    if (this.collection.get(item.id)) {
                        this.collection.remove(item.id);
                    }
                    model.set(item);
                    this.collection.add(model);
                })
            });
            callback();
        },

        compareEntities(groupKey) {
            let group = this.groups.find(group => group.key === groupKey);
            this.getCollectionFactory().create(group.key, collection => {
                collection.sortBy = 'name';
                collection.where = [
                    {
                        "type": "bool",
                        "value": ['onlyBookmarked']
                    }
                ];

                if (group.collection.length < 2) {
                    this.notify('Should I have at list 2 records in bookmarks to be able to compare');
                } else {
                    this.createView('dialog', 'views/modals/compare', {
                        collection: collection,
                        scope: group.key,
                        mode: "details",
                        className: 'full-page-modal',
                        hideRelationship: false
                    }, function (dialog) {
                        dialog.render();
                        this.notify(false)
                    })
                }
            })
        }
    })
});
