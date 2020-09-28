

Espo.define('views/modals/action-history', ['views/modal', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        header: false,

        scope: 'ActionHistoryRecord',

        className: 'dialog dialog-record',

        template: 'modals/action-history',

        backdrop: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            this.scope = this.entityType = this.options.scope || this.scope;

            this.header = this.getLanguage().translate(this.scope, 'scopeNamesPlural');
            this.header = '<a href="#ActionHistoryRecord" class="action" data-action="listView">' + this.header + '</a>';

            this.waitForView('list');

            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPage') || 20;
                this.collection = collection;

                this.loadSearch();
                this.loadList();
                collection.fetch();
            }, this);

        },

        actionListView: function () {
            this.getRouter().navigate('#ActionHistoryRecord', {trigger: true});
            this.close();
        },

        loadSearch: function () {
            var searchManager = this.searchManager = new SearchManager(this.collection, 'listSelect', null, this.getDateTime());

            this.collection.data.boolFilterList = ['onlyMy'];

            this.collection.where = searchManager.getWhere();


            this.createView('search', 'views/record/search', {
                collection: this.collection,
                el: this.containerSelector + ' .search-container',
                searchManager: searchManager,
                disableSavePreset: true,
                textFilterDisabled: true
            });
        },

        loadList: function () {
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') ||
                           'views/record/list';

            this.listenToOnce(this.collection, 'sync', function () {
                this.createView('list', viewName, {
                    collection: this.collection,
                    el: this.containerSelector + ' .list-container',
                    selectable: false,
                    checkboxes: false,
                    massActionsDisabled: true,
                    rowActionsView: 'views/record/row-actions/view-only',
                    type: 'listSmall',
                    searchManager: this.searchManager,
                    checkAllResultDisabled: true,
                    buttonsDisabled: true
                });
            }, this);
        },
    });
});

