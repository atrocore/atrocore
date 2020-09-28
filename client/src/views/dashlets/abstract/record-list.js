

Espo.define('views/dashlets/abstract/record-list', ['views/dashlets/abstract/base', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        scope: null,

        listViewColumn: 'views/record/list',

        listViewExpanded: 'views/record/list-expanded',

        listView: null,

        _template: '<div class="list-container">{{{list}}}</div>',

        layoutType: 'expanded',

        optionsFields: _.extend(_.clone(Dep.prototype.optionsFields), {
            'displayRecords': {
                type: 'enumInt',
                options: [3,4,5,10,15],
            }
        }),

        rowActionsView: 'views/record/row-actions/view-and-edit',

        init: function () {
            this.scope = this.getMetadata().get(['dashlets', this.name, 'entityType']) || this.scope;
            Dep.prototype.init.call(this);
        },

        checkAccess: function () {
            return this.getAcl().check(this.scope, 'read');
        },

        getSearchData: function () {
            return this.getOption('searchData');
        },

        afterRender: function () {
            this.getCollectionFactory().create(this.scope, function (collection) {
                var searchData = this.getSearchData();

                var searchManager = this.searchManager = new SearchManager(collection, 'list', null, this.getDateTime(), searchData);

                if (!this.scope) {
                    this.$el.find('.list-container').html(this.translate('selectEntityType', 'messages', 'DashletOptions'));
                    return;
                }

                if (!this.checkAccess()) {
                    this.$el.find('.list-container').html(this.translate('No Access'));
                    return;
                }

                if (this.collectionUrl) {
                    collection.url = this.collectionUrl;
                }

                this.collection = collection;
                collection.sortBy = this.getOption('sortBy') || this.collection.sortBy;
                collection.asc = this.getOption('asc') || this.collection.asc;

                if (this.getOption('sortDirection') === 'asc') {
                    collection.asc = true;
                } else if (this.getOption('sortDirection') === 'desc') {
                    collection.asc = false;
                }

                collection.maxSize = this.getOption('displayRecords');
                collection.where = searchManager.getWhere();

                var viewName = this.listView || ((this.layoutType == 'expanded') ? this.listViewExpanded : this.listViewColumn);

                this.createView('list', viewName, {
                    collection: collection,
                    el: this.getSelector() + ' .list-container',
                    pagination: this.getOption('pagination') ? 'bottom' : false,
                    type: 'listDashlet',
                    rowActionsView: this.rowActionsView,
                    checkboxes: false,
                    showMore: true,
                    listLayout: this.getOption(this.layoutType + 'Layout'),
                    skipBuildRows: true
                }, function (view) {
                    view.getSelectAttributeList(function (selectAttributeList) {
                        if (selectAttributeList) {
                            collection.data.select = selectAttributeList.join(',');
                        }
                        collection.fetch();
                    }.bind(this));
                });

            }, this);
        },

        setupActionList: function () {
            if (this.scope && this.getAcl().checkScope(this.scope, 'create')) {
                this.actionList.unshift({
                    name: 'create',
                    html: this.translate('Create ' + this.scope, 'labels', this.scope),
                    iconHtml: '<span class="fas fa-plus"></span>',
                    url: '#'+this.scope+'/create'
                });
            }
        },

        actionRefresh: function () {
            if (!this.collection) return;

            this.collection.where = this.searchManager.getWhere();
            this.collection.fetch();
        },

        actionCreate: function () {
            var attributes = this.getCreateAttributes() || {};

            if (this.getOption('populateAssignedUser')) {
                if (this.getMetadata().get(['entityDefs', this.scope, 'fields', 'assignedUsers'])) {
                    attributes['assignedUsersIds'] = [this.getUser().id];
                    attributes['assignedUsersNames'] = {};
                    attributes['assignedUsersNames'][this.getUser().id] = this.getUser().get('name');
                } else {
                    attributes['assignedUserId'] = this.getUser().id;
                    attributes['assignedUserName'] = this.getUser().get('name');
                }
            }

            this.notify('Loading...');
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.edit') || 'views/modals/edit';
            this.createView('modal', viewName, {
                scope: this.scope,
                attributes: attributes,
            }, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.actionRefresh();
                }, this);
            }.bind(this));
        },

        getCreateAttributes: function () {

        }
    });
});

